## sunny/item-scanner

Identifies the items in a photo with Apple's on-device Foundation Models (iOS 27+, Apple Intelligence devices). iOS only.

@verbatim
<code-snippet name="Identifying items in a photo" lang="php">
use Sunny\ItemScanner\Events\IdentificationFailed;
use Sunny\ItemScanner\Events\ItemsIdentified;
use Sunny\ItemScanner\Facades\ItemScanner;

// ['available' => bool, 'reason' => ?string]
$availability = ItemScanner::availability();

$id = ItemScanner::identify($photoPath, knownNames: ['Cordless drill'], place: 'Garage › Tool chest');

#[On(ItemsIdentified::class)]
public function itemsIdentified(string $id, array $items): void {}

#[On(IdentificationFailed::class)]
public function identificationFailed(string $id, string $message): void {}
</code-snippet>
@endverbatim

- Each item has `name`, `category`, `brand` (nullable), `model` (nullable), `quantity`, and `existingMatch` (one of `knownNames`, or null).
- `reason` codes: `bridgeUnavailable`, `unsupportedOS`, `deviceNotEligible`, `appleIntelligenceNotEnabled`, `modelNotReady`, `visionUnsupported`.
