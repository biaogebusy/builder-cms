
***
## <a name="scope-and-responsibilities"> </a>SCOPE & RESPONSIBILITIES

Blazy is scoped to **media delivery** primitives, ecosystem coordination,
and optional semantic structure; layout composition, interaction behavior,
and **Core Web Vitals** results depend on the consuming theme and application.

In this context, *primitives* refers to browser-level mechanisms that directly
influence how media enters the rendering pipeline, including request
scheduling (`preload`, `fetchpriority`), decoding and loading timing, intrinsic
sizing and layout reservation, and safe DOM replacement for placeholders. These
controls determine *when* media is fetched, *how* it is decoded, and *whether*
it introduces layout instability.

UI-level features such as media players and lightboxes are considered optional.
They are treated as downstream consumers of media, not as part of the delivery
pipeline itself. Blazy focuses on shaping the low-level behaviors that browsers
act on and that **Core Web Vitals** later measure, while final user experience
and visual outcomes remain application responsibilities.

---
<a href="#top">Back to Top &uarr;</a>
---
