/**
 * This file is testing 4 Dependencies of both types, scripts and packages (scripts with @root)
 * The focus is on testing the @nocompile annotation.
 *
 * The output should be that some/nocompile/package.js will be referenced in the manifest
 * instead of some/nocompile/package.compiled.js.
 *
 * -
 * TODO these tests need an easily machine detectable way to indicate before/after compilation.
 *      For example: a variable named thisisavariable. After compilation that string should not be present in the code
 *      but 'a' should be.
 *
 * -
 * Memo for the future:
 * The some/nocompile/script.js should be compiled in normally, as without an inline require annotation, it does not
 * really make great sense to merge together compiled and uncompiled in one file. It does make sense, but it is a
 * code smell. I really dislike having the annotation at all honestly, but I see the practical uses for it.
 *
 * In the future this should support merging together it all, in which case script should come in uncompiled.
 *
 */

// @require some/nocompile/package.js
// @require some/nocompile/script.js
// @require some/normal/package.js
// @require some/normal/script.js

window.main = true;