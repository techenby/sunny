---
paths:
  - 'resources/views/native/**'
---

# Native

## Press feedback on pressables
Kit buttons (`x-ui.button`) dim while pressed with `press-opacity`. On nativephp/mobile 4.7.0 this does not block scrolling: checked on an iPhone on 2026-11-22 with buttons inside a vertical scroll view. Earlier versions implemented press feedback with a zero-distance DragGesture that stole the scroll gesture, which is why the pressables that fill the Summary carousel have none. That case has not been retested, so leave `press-scale` / `press-opacity` / `press-translate-y` off pressables that fill a carousel until it is.
