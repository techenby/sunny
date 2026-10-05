import AVFoundation
import UIKit

/// A full-screen camera that stays open between shots, so a batch of items
/// can be photographed one after another. Each photo is sent to PHP as a
/// PhotoCaptured event the moment it's saved. With `single`, it closes after
/// the first shot instead.
final class ItemCaptureViewController: UIViewController, AVCapturePhotoCaptureDelegate {
    private let captureId: String
    private let single: Bool

    private let session = AVCaptureSession()
    private let photoOutput = AVCapturePhotoOutput()
    private let sessionQueue = DispatchQueue(label: "sunny.item-scanner.capture")
    private let previewLayer: AVCaptureVideoPreviewLayer

    private let shutterButton = UIButton(type: .custom)
    private let doneButton = UIButton(type: .system)
    private let countLabel = UILabel()
    private let thumbnailView = UIImageView()
    private let flashView = UIView()
    private let haptics = UIImpactFeedbackGenerator(style: .medium)

    private var captured = 0

    init(captureId: String, single: Bool) {
        self.captureId = captureId
        self.single = single
        self.previewLayer = AVCaptureVideoPreviewLayer(session: session)
        super.init(nibName: nil, bundle: nil)
        modalPresentationStyle = .fullScreen
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override var prefersStatusBarHidden: Bool { true }

    override var supportedInterfaceOrientations: UIInterfaceOrientationMask { .portrait }

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .black

        previewLayer.videoGravity = .resizeAspectFill
        view.layer.addSublayer(previewLayer)

        setUpControls()
        haptics.prepare()

        sessionQueue.async { [weak self] in
            self?.configureSession()
        }
    }

    override func viewDidLayoutSubviews() {
        super.viewDidLayoutSubviews()
        previewLayer.frame = view.bounds
    }

    override func viewWillDisappear(_ animated: Bool) {
        super.viewWillDisappear(animated)

        sessionQueue.async { [session] in
            session.stopRunning()
        }
    }

    // MARK: - Session

    private func configureSession() {
        session.beginConfiguration()
        session.sessionPreset = .photo

        guard let camera = AVCaptureDevice.default(.builtInWideAngleCamera, for: .video, position: .back),
              let input = try? AVCaptureDeviceInput(device: camera),
              session.canAddInput(input),
              session.canAddOutput(photoOutput) else {
            session.commitConfiguration()
            fail("This iPhone's camera isn't available right now.")
            return
        }

        session.addInput(input)
        session.addOutput(photoOutput)
        photoOutput.maxPhotoQualityPrioritization = .speed
        session.commitConfiguration()
        session.startRunning()
    }

    private func fail(_ message: String) {
        DispatchQueue.main.async { [self] in
            ItemScannerEvents.captureFailed(id: captureId, message: message)
            dismiss(animated: true)
        }
    }

    // MARK: - Controls

    private func setUpControls() {
        var shutterConfig = UIButton.Configuration.plain()
        shutterConfig.background.backgroundColor = .white
        shutterConfig.background.cornerRadius = 36
        shutterConfig.background.strokeColor = UIColor.white.withAlphaComponent(0.5)
        shutterConfig.background.strokeWidth = 6
        shutterConfig.background.strokeOutset = 6
        shutterButton.configuration = shutterConfig
        shutterButton.accessibilityLabel = "Take photo"
        shutterButton.addTarget(self, action: #selector(takePhoto), for: .touchUpInside)

        var doneConfig = UIButton.Configuration.filled()
        doneConfig.title = single ? "Cancel" : "Done"
        doneConfig.cornerStyle = .capsule
        doneConfig.baseBackgroundColor = UIColor.black.withAlphaComponent(0.5)
        doneConfig.baseForegroundColor = .white
        doneButton.configuration = doneConfig
        doneButton.addTarget(self, action: #selector(finish), for: .touchUpInside)

        countLabel.font = .preferredFont(forTextStyle: .headline)
        countLabel.textColor = .white
        countLabel.textAlignment = .center
        countLabel.isHidden = single
        countLabel.text = "Take a photo of each item"

        thumbnailView.contentMode = .scaleAspectFill
        thumbnailView.clipsToBounds = true
        thumbnailView.layer.cornerRadius = 10
        thumbnailView.layer.borderColor = UIColor.white.cgColor
        thumbnailView.layer.borderWidth = 2
        thumbnailView.isHidden = true

        flashView.backgroundColor = .white
        flashView.alpha = 0
        flashView.isUserInteractionEnabled = false

        for control in [flashView, shutterButton, doneButton, countLabel, thumbnailView] {
            control.translatesAutoresizingMaskIntoConstraints = false
            view.addSubview(control)
        }

        let guide = view.safeAreaLayoutGuide

        NSLayoutConstraint.activate([
            flashView.topAnchor.constraint(equalTo: view.topAnchor),
            flashView.bottomAnchor.constraint(equalTo: view.bottomAnchor),
            flashView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            flashView.trailingAnchor.constraint(equalTo: view.trailingAnchor),

            shutterButton.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            shutterButton.bottomAnchor.constraint(equalTo: guide.bottomAnchor, constant: -24),
            shutterButton.widthAnchor.constraint(equalToConstant: 72),
            shutterButton.heightAnchor.constraint(equalToConstant: 72),

            thumbnailView.leadingAnchor.constraint(equalTo: guide.leadingAnchor, constant: 24),
            thumbnailView.centerYAnchor.constraint(equalTo: shutterButton.centerYAnchor),
            thumbnailView.widthAnchor.constraint(equalToConstant: 56),
            thumbnailView.heightAnchor.constraint(equalToConstant: 56),

            doneButton.trailingAnchor.constraint(equalTo: guide.trailingAnchor, constant: -24),
            doneButton.centerYAnchor.constraint(equalTo: shutterButton.centerYAnchor),

            countLabel.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            countLabel.topAnchor.constraint(equalTo: guide.topAnchor, constant: 16),
        ])
    }

    @objc private func takePhoto() {
        haptics.impactOccurred()

        flashView.alpha = 0.6
        UIView.animate(withDuration: 0.25) { self.flashView.alpha = 0 }

        if single {
            shutterButton.isEnabled = false
        }

        let settings = photoOutput.availablePhotoCodecTypes.contains(.jpeg)
            ? AVCapturePhotoSettings(format: [AVVideoCodecKey: AVVideoCodecType.jpeg])
            : AVCapturePhotoSettings()
        settings.photoQualityPrioritization = .speed

        sessionQueue.async { [self] in
            if let connection = photoOutput.connection(with: .video), connection.isVideoRotationAngleSupported(90) {
                connection.videoRotationAngle = 90
            }

            photoOutput.capturePhoto(with: settings, delegate: self)
        }
    }

    @objc private func finish() {
        dismiss(animated: true)
    }

    // MARK: - AVCapturePhotoCaptureDelegate

    func photoOutput(_ output: AVCapturePhotoOutput, didFinishProcessingPhoto photo: AVCapturePhoto, error: Error?) {
        guard error == nil, let data = photo.fileDataRepresentation(), let path = save(data) else {
            DispatchQueue.main.async { [self] in
                countLabel.text = "That photo didn't save. Try again."
                shutterButton.isEnabled = true
            }
            return
        }

        DispatchQueue.main.async { [self] in
            captured += 1
            countLabel.text = captured == 1 ? "1 item" : "\(captured) items"
            thumbnailView.image = UIImage(data: data)?.preparingThumbnail(of: CGSize(width: 112, height: 112))
            thumbnailView.isHidden = false

            ItemScannerEvents.captured(id: captureId, path: path)

            if single {
                dismiss(animated: true)
            }
        }
    }

    private func save(_ data: Data) -> String? {
        let directory = FileManager.default.temporaryDirectory.appendingPathComponent("ItemScanner", isDirectory: true)
        let url = directory.appendingPathComponent("\(UUID().uuidString).jpg")

        do {
            try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: true)
            try data.write(to: url)
        } catch {
            return nil
        }

        return url.path(percentEncoded: false)
    }
}
