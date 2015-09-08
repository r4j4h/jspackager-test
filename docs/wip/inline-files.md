# WIP

# Inline Requires

Inline scripts! One can see that as taking a wrapper and injecting foo.js into it. Or stitching a pre-wrap, foo.js, and post-wrap.

jsVars should returns its fragment as a script tag and before or after should just be normal script tags.
In that case jsVars would not be a compositeFile, so it is irrelevant as far as wrapping or modifying contents is concerned.


### Test case:

```javascript
foo.js
var foo = {};
```

```javascript
baz.js
var baz = {};
```

```javascript
main.js
// @requireInline foo.js
var bar = {};
// @requireInline baz.js
```


right now @require moves foo and baz before main, much like hoisting, but this way we should be able to have foo, bar, and then baz!

Definitely the right move. But we need to splice the files in a complex way. Thing of extending that example. main must be split into three fragments each time, as we are bisecting it.

There is nothing substantial before the foo require, so it can be discarded.
Foo's entire contents must be included.
Main must have a fragment created up until the next require annotation (which annotations bisect like this? @root probably doesn't need to)
Main's pre-next-annotation fragment must be included
Baz's entire contents must be included.
Main's pre-next-annotation fragment must be included
Done.

Things to be recorded for a bisection: line of code the annotation occurs on

### Potential pitfalls:
- If foo.js or baz.js has a @require, how/when is that handled?