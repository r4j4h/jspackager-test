/**
 * This file is testing 4 Dependencies of both types, scripts and packages (scripts with @root)
 * The focus is on testing the @nocompile annotation.
 *
 * The output should be that some/nocompile/package.js will be referenced in the manifest
 * and that some/normal/script.js should be included uncompiled.
 */

// @require some/nocompile/package.js
// @require some/nocompile/script.js
// @require some/normal/package.js
// @require some/normal/script.js

window.main = true;