## sunny/item-scanner

Identifies the item in a photo with Apple's on-device Foundation Models (iOS 27+, Apple Intelligence devices). iOS only.

@verbatim
<code-snippet name="Identifying the item in a photo" lang="php">
use Sunny\ItemScanner\Events\CaptureFailed;
use Sunny\ItemScanner\Events\IdentificationFailed;
use Sunny\ItemScanner\Events\ItemIdentified;
use Sunny\ItemScanner\Events\PhotoCaptured;
use Sunny\ItemScanner\Facades\ItemScanner;

// ['available' => bool, 'reason' => ?string]
$availability = ItemScanner::availability();

$id = ItemScanner::identify($photoPath, place: 'Garage › Tool chest', batch: 'Christmas ornaments', batchNames: ['Glass snowman']);

#[On(ItemIdentified::class)]
public function itemIdentified(string $id, array $item): void {}

#[On(IdentificationFailed::class)]
public function identificationFailed(string $id, string $message): void {}

// A camera that stays open between shots; pass single: true to close after one.
$captureId = ItemScanner::capture();

#[On(PhotoCaptured::class)]
public function photoCaptured(string $id, string $path): void {}

#[On(CaptureFailed::class)]
public function captureFailed(string $id, string $message): void {}
</code-snippet>
@endverbatim

- The item has `name` (may be empty when the model couldn't tell), `category`, `brand` (nullable), `model` (nullable), and `quantity`.
- `reason` codes: `bridgeUnavailable`, `unsupportedOS`, `deviceNotEligible`, `appleIntelligenceNotEnabled`, `modelNotReady`, `visionUnsupported`.
