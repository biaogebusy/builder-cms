

# ABOUT DEJAVU NAMESPACE
This contains re-usable methods across several modules:
* Slick
* Slick Views
* Slick Media
* Mason
* GridStack
* ... counting.

Those modules do not all necessarily use the Blazy JS library, however
this module is a reasonable place to reduce duplication efforts and DRY stuffs.

A few things that bring those module here:
* All makes use of various lazyload goodness: IMG, IFRAME, and DIV/BODY, etc.,
  which is not currently available at D8, nor D7 AFAIK.
* All has things in common, that is, working towards performant image and media.

Please ignore the namespace. The point is to not repeat.
