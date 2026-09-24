import Foundation
import FoundationModels
import ImageIO
import Vision

// MARK: - ItemScanner Function Namespace

/// Bridge functions for identifying the items in a photo with Apple's
/// on-device Foundation Models.
/// Namespace: "ItemScanner.*"
///
/// Identification runs off the calling thread; results come back to PHP as
/// ItemsIdentified or IdentificationFailed events carrying the request id.
enum ItemScannerFunctions {

    // MARK: - ItemScanner.Availability

    /// Returns `available` and, when it's false, a `reason` code:
    /// unsupportedOS, deviceNotEligible, appleIntelligenceNotEnabled,
    /// modelNotReady, or visionUnsupported.
    class Availability: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let reason = ItemScannerModel.unavailableReason()

            return BridgeResponse.success(data: [
                "available": reason == nil,
                "reason": reason ?? NSNull(),
            ])
        }
    }

    // MARK: - ItemScanner.Identify

    /// Parameters:
    ///   - path: string - the photo to identify items in
    ///   - id: string - echoed back on the result event
    ///   - knownNames: [string] - (optional) items already in inventory, for duplicate matching
    ///   - place: string - (optional) where the photo was taken, e.g. "Garage › Tool chest"
    class Identify: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let path = parameters["path"] as? String, !path.isEmpty else {
                throw BridgeError.invalidParameters("path is required")
            }

            let id = (parameters["id"] as? String).flatMap { $0.isEmpty ? nil : $0 } ?? UUID().uuidString.lowercased()
            let knownNames = parameters["knownNames"] as? [String] ?? []
            let place = (parameters["place"] as? String).flatMap { $0.isEmpty ? nil : $0 }

            guard #available(iOS 27.0, *) else {
                ItemScannerEvents.failed(id: id, message: "Scanning needs iOS 27 or later.")
                return BridgeResponse.success(data: ["started": false, "id": id])
            }

            Task.detached(priority: .userInitiated) {
                await ItemScannerModel.identify(id: id, path: path, knownNames: knownNames, place: place)
            }

            return BridgeResponse.success(data: ["started": true, "id": id])
        }
    }
}

// MARK: - Events

enum ItemScannerEvents {
    static func identified(id: String, items: [[String: Any]]) {
        send("Sunny\\ItemScanner\\Events\\ItemsIdentified", ["id": id, "items": items])
    }

    static func failed(id: String, message: String) {
        send("Sunny\\ItemScanner\\Events\\IdentificationFailed", ["id": id, "message": message])
    }

    private static func send(_ event: String, _ payload: [String: Any]) {
        DispatchQueue.main.async {
            LaravelBridge.shared.send?(event, payload)
        }
    }
}

// MARK: - Model

enum ItemScannerModel {
    /// The longest edge the photo is scaled to before it reaches the model.
    /// Larger images cost more of the small context window for little gain.
    static let maxPixelSize = 1024

    /// Cap on the existing-item names sent for duplicate matching, so the
    /// prompt stays well inside the on-device context window.
    static let maxKnownNames = 150

    static func unavailableReason() -> String? {
        guard #available(iOS 27.0, *) else {
            return "unsupportedOS"
        }

        let model = SystemLanguageModel.default

        switch model.availability {
        case .available:
            return model.capabilities.contains(.vision) ? nil : "visionUnsupported"
        case .unavailable(.deviceNotEligible):
            return "deviceNotEligible"
        case .unavailable(.appleIntelligenceNotEnabled):
            return "appleIntelligenceNotEnabled"
        case .unavailable(.modelNotReady):
            return "modelNotReady"
        case .unavailable:
            return "modelNotReady"
        }
    }

    @available(iOS 27.0, *)
    static func identify(id: String, path: String, knownNames: [String], place: String?) async {
        if let reason = unavailableReason() {
            ItemScannerEvents.failed(id: id, message: message(forUnavailable: reason))
            return
        }

        guard let image = loadImage(path: path) else {
            ItemScannerEvents.failed(id: id, message: "Couldn't read that photo. Try taking it again.")
            return
        }

        let session = LanguageModelSession(tools: [OCRTool()], instructions: instructions)
        let names = Array(knownNames.prefix(maxKnownNames))

        do {
            let response = try await session.respond(generating: ScannedItems.self) {
                prompt(knownNames: names, place: place)
                Attachment(image)
            }

            let items = response.content.items.compactMap { item -> [String: Any]? in
                let name = item.name.trimmingCharacters(in: .whitespacesAndNewlines)

                guard !name.isEmpty else {
                    return nil
                }

                return [
                    "name": name,
                    "category": item.category,
                    "brand": item.brand ?? NSNull(),
                    "model": item.model ?? NSNull(),
                    "quantity": max(1, item.quantity),
                    "existingMatch": item.existingMatch.flatMap { names.contains($0) ? $0 : nil } ?? NSNull(),
                ]
            }

            ItemScannerEvents.identified(id: id, items: items)
        } catch let error as LanguageModelSession.GenerationError {
            ItemScannerEvents.failed(id: id, message: message(for: error))
        } catch {
            ItemScannerEvents.failed(id: id, message: "Couldn't identify the items in that photo. Try again.")
        }
    }

    static let instructions = """
        You catalog the contents of a home for a household inventory app. \
        Look at the photo and list each distinct physical object someone would want to keep track of. \
        Ignore furniture, walls, floors, shelving and other fixtures unless they are clearly the subject. \
        Use short, plain names a person would search for, like "Cordless drill" or "Cast iron skillet". \
        Only give a brand or model when it is printed on the item or unmistakable; use the OCR tool to read labels. \
        List identical items once and set the quantity instead.
        """

    static func prompt(knownNames: [String], place: String?) -> String {
        var lines = ["List the items in this photo."]

        if let place {
            lines.append("The photo was taken in: \(place).")
        }

        if !knownNames.isEmpty {
            lines.append("These items are already in the inventory there. When an item in the photo is one of them, set existingMatch to its exact name:")
            lines.append(contentsOf: knownNames.map { "- \($0)" })
        }

        return lines.joined(separator: "\n")
    }

    /// Decodes anything ImageIO understands (HEIC, JPEG, PNG, …), applies the
    /// EXIF orientation, and downscales in one pass.
    static func loadImage(path: String) -> CGImage? {
        let url = path.hasPrefix("file://") ? URL(string: path) : URL(fileURLWithPath: path)

        guard let url, let source = CGImageSourceCreateWithURL(url as CFURL, nil) else {
            return nil
        }

        let options: [CFString: Any] = [
            kCGImageSourceCreateThumbnailFromImageAlways: true,
            kCGImageSourceCreateThumbnailWithTransform: true,
            kCGImageSourceThumbnailMaxPixelSize: maxPixelSize,
        ]

        return CGImageSourceCreateThumbnailAtIndex(source, 0, options as CFDictionary)
    }

    static func message(forUnavailable reason: String) -> String {
        switch reason {
        case "unsupportedOS":
            return "Scanning needs iOS 27 or later."
        case "deviceNotEligible", "visionUnsupported":
            return "This device can't run Apple Intelligence, which scanning needs."
        case "appleIntelligenceNotEnabled":
            return "Turn on Apple Intelligence in Settings to scan items."
        default:
            return "Apple Intelligence is still getting ready. Try again in a few minutes."
        }
    }

    @available(iOS 27.0, *)
    static func message(for error: LanguageModelSession.GenerationError) -> String {
        switch error {
        case .exceededContextWindowSize:
            return "That photo has too much going on. Try a closer shot of fewer items."
        case .guardrailViolation, .refusal:
            return "Apple Intelligence couldn't describe that photo. Try a different shot."
        case .assetsUnavailable:
            return "Apple Intelligence is still getting ready. Try again in a few minutes."
        case .rateLimited, .concurrentRequests:
            return "Apple Intelligence is busy. Wait a moment and try again."
        default:
            return "Couldn't identify the items in that photo. Try again."
        }
    }
}

// MARK: - Structured output

@available(iOS 27.0, *)
@Generable
struct ScannedItems {
    @Guide(description: "Every distinct item worth tracking in the photo", .maximumCount(25))
    var items: [ScannedItem]
}

@available(iOS 27.0, *)
@Generable
struct ScannedItem {
    @Guide(description: "A short, plain name for the item, like \"Cordless drill\"")
    var name: String

    @Guide(description: "A broad category, like Tools, Kitchen, Electronics, Decor, or Clothing")
    var category: String

    @Guide(description: "The brand, only when printed on the item or unmistakable")
    var brand: String?

    @Guide(description: "The model name or number, only when printed on the item")
    var model: String?

    @Guide(description: "How many of this item are visible", .range(1...99))
    var quantity: Int

    @Guide(description: "The exact name from the existing inventory list when this is the same item")
    var existingMatch: String?
}
