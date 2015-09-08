# WIP

# Wrapping Files


### Test case:

```javascript
test.js
var wrapped = true;
```

```javascript
goal.js
(function(dep) {
	var wrapped = true;
})(window || {});
```


// Take a File and wrap it, getting a new File back representing the wrapped original File ready for inclusion or compilation
// When to use? When wrapping for containing globals or making available in a different loading/packaging system
Operation wrapFooIntoCompositeFile(string foo.js)
1. Let fooFile be File(foo.js), foo.js is stat'd but not read
2. fooFile->wrap(array('dep' => 'window || {}'));
3. Let foo'sFile be Fragment('(function(dep) {')
4. Let foo'eFile be Fragment('})(window || {});')
5. Let foo'' be CompositeFile(foo'sFile, fooFile, foo'eFile)

// Get a File as a valid tag ready for inclusion on a page
// Files within basePath are turned as tags linking to the files
// Files outside basePath are rendered as strings and embedded as script tags on the page
// When would I want to use? When I want to pull in on a page.
Operation printFooToHtml(CompositeFile cFile)
1. cFile->toTag() called
2. CompositeFile loops through its three acting as a large fragment
3. Let tmpFile be /tmp/c
3. fragment for begin wrap returns '(function(dep) {\n' streamed into tmpFile
4. foo.js is file so fooFile->getContents returns 'var wrapped = true;' streamed into tmpFile
5. fragment for end wrap returns '})(window || {});' streamed into tmpFile
6. let fragment be tmpFile read as string
6. CompositeFile wraps fragment in '<script>'.{fragment}.'</script>' and returns string

// Get a File as separate files wat does this even mean? CompositeFile specific function to get the underlying Files?
// When would I want to use? Unit tests or introspection.
Operation getFooAsFiles(CompositeFile cFile)
1. cFile->toFiles() called
2. CompositeFile returns [foo'sFile, fooFile, foo'eFile]

// Get a File as a file path
// When would I want to use? Wanting to use something that requires file paths for input.
Operation getfooAsFile(CompositeFile cFile)
1. cFile->getFilePath() called
2. CompositeFile loops through its three acting as a large fragment
3. Let tmpFile be /tmp/c
3. fragment for begin wrap returns '(function(dep) {\n' streamed into tmpFile
4. foo.js is file so fooFile->getContents returns 'var wrapped = true;' streamed into tmpFile
5. fragment for end wrap returns '})(window || {});' streamed into tmpFile
6. CompositeFile returns tmpFile's path '/tmp/c' as string



5. Coerce foo'sFile to string and stream to /tmp/0
3. foo is stream loaded into memory or a /tmp file as foo'
4. foo' is prepended and appended with the wrapping fragments.
5. File(foo.js) now represents foo'
6. Gets included on page as a script fragment

We would NOT want to put a /tmp file in a html src tag, we would rather put contents in a <script></script>
We would be able to pass as a /tmp file to closure compiler

How is that cached?

Compiler
1. File foo' = wrapFoo(foo.js)
2. foo' put to file system
3. foo' pass to GCC
4. foo'' created
5. foo'' loaded on page

foo''

Could we do that by keeping foo.js unmodified and having two fragment files?

Operation cleverWrapFoo(foo.js)
1. Create File(foo.js)
2. foo.js is stat'd but not read
3. foo is stream loaded into memory or /tmp file as foo'
4. foo is turned into CompositeFile representing fragment for begin wrap, foo.js, and fragment for post wrap
5. compositeFile returned

compile
compositeFile needs to go to GCC
1. File->toFileSystem() called
2. CompositeFile loops through its three acting as a large fragment needing to be returned as a file
3. fragment for begin wrap goes to /tmp/cwf/0 and returns abs path
4. foo.js returns abs path
5. fragment for end wrap goes to /tmp/cwf/1 and returns abs path
6. CompositeFile returns its file as three sequential files
6. GCC gets /tmp/cwf0 /path/to/foo.js /tmp/cwf1
7. compiledFile returned

use
compositeFile needs to be included on page
