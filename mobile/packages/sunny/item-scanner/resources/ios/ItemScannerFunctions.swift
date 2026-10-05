import AVFoundation
import Foundation
import FoundationModels
import ImageIO
import UIKit
import Vision

// MARK: - ItemScanner Function Namespace

/// Bridge functions for identifying the item in a photo with Apple's
/// on-device Foundation Models.
/// Namespace: "ItemScanner.*"
///
/// Identification runs off the calling thread; results come back to PHP as
/// ItemIdentified or IdentificationFailed events carrying the request id.
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
    ///   - id: string - chosen by PHP and echoed back on the result event
    ///   - path: string - the photo to identify the item in
    ///   - place: string - (optional) where the photo was taken, e.g. "Garage › Tool chest"
    ///   - batch: string - (optional) what the batch of photos is of, e.g. "Christmas ornaments"
    ///   - batchNames: [string] - (optional) names already given to other items in the batch
    ///
    /// Once there is an id, exactly one result event is sent for it.
    class Identify: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let id = parameters["id"] as? String, !id.isEmpty else {
                throw BridgeError.invalidParameters("id is required")
            }

            guard let path = parameters["path"] as? String, !path.isEmpty else {
                ItemScannerEvents.failed(id: id, message: ItemScannerModel.unreadablePhotoMessage)
                return BridgeResponse.success(data: ["started": false, "id": id])
            }

            let place = (parameters["place"] as? String).flatMap { $0.isEmpty ? nil : $0 }
            let batch = (parameters["batch"] as? String).flatMap { $0.isEmpty ? nil : $0 }
            let batchNames = Array((parameters["batchNames"] as? [String] ?? []).prefix(ItemScannerModel.maxBatchNames))

            guard #available(iOS 27.0, *) else {
                ItemScannerEvents.failed(id: id, message: ItemScannerModel.message(forUnavailable: "unsupportedOS"))
                return BridgeResponse.success(data: ["started": false, "id": id])
            }

            Task.detached(priority: .userInitiated) {
                await ItemScannerModel.identify(id: id, path: path, place: place, batch: batch, batchNames: batchNames)
            }

            return BridgeResponse.success(data: ["started": true, "id": id])
        }
    }

    // MARK: - ItemScanner.Capture

    /// Parameters:
    ///   - id: string - chosen by PHP and echoed back on every event
    ///   - single: bool - (optional) close the camera after one photo
    ///
    /// Opens a camera that stays up between shots, sending a PhotoCaptured
    /// event for each photo, or CaptureFailed when it can't open.
    class Capture: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let id = parameters["id"] as? String, !id.isEmpty else {
                throw BridgeError.invalidParameters("id is required")
            }

            let single = parameters["single"] as? Bool ?? false

            switch AVCaptureDevice.authorizationStatus(for: .video) {
            case .authorized:
                present(id: id, single: single)
            case .notDetermined:
                AVCaptureDevice.requestAccess(for: .video) { granted in
                    if granted {
                        self.present(id: id, single: single)
                    } else {
                        ItemScannerEvents.captureFailed(id: id, message: ItemScannerModel.cameraDeniedMessage)
                    }
                }
            default:
                ItemScannerEvents.captureFailed(id: id, message: ItemScannerModel.cameraDeniedMessage)
            }

            return BridgeResponse.success(data: ["id": id])
        }

        private func present(id: String, single: Bool) {
            DispatchQueue.main.async {
                guard let windowScene = UIApplication.shared.connectedScenes
                    .compactMap({ $0 as? UIWindowScene })
                    .first(where: { $0.activationState == .foregroundActive }),
                      var top = windowScene.windows.first(where: { $0.isKeyWindow })?.rootViewController else {
                    ItemScannerEvents.captureFailed(id: id, message: "Couldn't open the camera. Try again.")
                    return
                }

                while let presented = top.presentedViewController {
                    top = presented
                }

                top.present(ItemCaptureViewController(captureId: id, single: single), animated: true)
            }
        }
    }
}

// MARK: - Events

enum ItemScannerEvents {
    static func identified(id: String, item: [String: Any]) {
        send("Sunny\\ItemScanner\\Events\\ItemIdentified", ["id": id, "item": item])
    }

    static func failed(id: String, message: String, detail: String? = nil) {
        send("Sunny\\ItemScanner\\Events\\IdentificationFailed", ["id": id, "message": message, "detail": detail ?? NSNull()])
    }

    static func captured(id: String, path: String) {
        send("Sunny\\ItemScanner\\Events\\PhotoCaptured", ["id": id, "path": path])
    }

    static func captureFailed(id: String, message: String) {
        send("Sunny\\ItemScanner\\Events\\CaptureFailed", ["id": id, "message": message])
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

    /// Cap on the batch names sent with each photo, so the prompt stays well
    /// inside the on-device context window.
    static let maxBatchNames = 40

    static let unreadablePhotoMessage = "Couldn't read that photo. Try taking it again."

    static let cameraDeniedMessage = "Allow camera access for Sunny in Settings to scan items."

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
    static func identify(id: String, path: String, place: String?, batch: String?, batchNames: [String]) async {
        if let reason = unavailableReason() {
            ItemScannerEvents.failed(id: id, message: message(forUnavailable: reason))
            return
        }

        guard let image = loadImage(path: path) else {
            ItemScannerEvents.failed(id: id, message: unreadablePhotoMessage)
            return
        }

        let request = prompt(place: place, batch: batch, batchNames: batchNames)

        do {
            let session = LanguageModelSession(instructions: instructions)

            let item = try await session.respond(generating: ScannedItem.self) {
                request
                Attachment(image)
            }.content

            ItemScannerEvents.identified(id: id, item: [
                "name": item.name.trimmingCharacters(in: .whitespacesAndNewlines),
                "category": item.category,
                "quantity": max(1, item.quantity),
            ])
        } catch let error as LanguageModelSession.GenerationError {
            ItemScannerEvents.failed(id: id, message: message(for: error), detail: String(describing: error))
        } catch {
            ItemScannerEvents.failed(id: id, message: "Couldn't identify that item. Type a name instead.", detail: String(describing: error))
        }
    }

    static let instructions = """
        You catalog the contents of a home for a household inventory app. \
        Each photo shows one thing someone wants to keep track of. Identify the main subject of the photo. \
        Ignore the background, furniture, walls, floors, shelving and other fixtures unless they are clearly the subject. \
        Use a short, plain name a person would search for, like "Cordless drill" or "Cast iron skillet". \
        When several identical items are the subject, set the quantity instead of describing them separately.
        """

    static func prompt(place: String?, batch: String?, batchNames: [String]) -> String {
        var lines = ["Identify the item in this photo."]

        if let place {
            lines.append("The photo was taken in: \(place).")
        }

        if let batch {
            lines.append("It is one of a batch of \(batch). Name what sets this one apart from the rest, like its color, material, shape, or design.")
        }

        if !batchNames.isEmpty {
            lines.append("Other items in the batch are already named: \(batchNames.joined(separator: "; ")). Give this one a different name unless it is clearly the same kind of thing.")
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
            return "That photo has too much going on. Try a closer shot of the item."
        case .guardrailViolation, .refusal:
            return "Apple Intelligence couldn't describe that photo. Type a name or retake it."
        case .assetsUnavailable:
            return "Apple Intelligence is still getting ready. Try again in a few minutes."
        case .rateLimited, .concurrentRequests:
            return "Apple Intelligence is busy. Wait a moment and try again."
        default:
            return "Couldn't identify that item. Type a name instead."
        }
    }
}

// MARK: - Structured output

@available(iOS 27.0, *)
@Generable
struct ScannedItem {
    @Guide(description: "A short, plain name for the item, like \"Cordless drill\"")
    var name: String

    @Guide(description: "A broad category, like Tools, Kitchen, Electronics, Decor, or Clothing")
    var category: String

    @Guide(description: "How many of this item are visible", .range(1...99))
    var quantity: Int
}
