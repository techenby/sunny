# sunny/item-scanner

A NativePHP Mobile plugin that identifies the item in a photo with Apple's on-device Foundation Models. Nothing leaves the device.

Identifying needs iOS 27 on an Apple Intelligence device. On anything else, `ItemScanner::availability()` reports why it can't run. The capture camera works on any supported iOS version.

See `resources/boost/guidelines/core.blade.php` for usage.
