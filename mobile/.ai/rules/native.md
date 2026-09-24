---
paths:
  - 'resources/views/native/**'
---

# Native

## No press feedback on pressables inside scrolling containers
Don't put `press-scale` / `press-opacity` / `press-translate-y` on pressables that fill a carousel or other scroll view. On iOS, NativePHP implements press feedback with a zero-distance DragGesture that steals the scroll gesture, so the carousel can't be dragged. Rely on the tap alone for those elements.
