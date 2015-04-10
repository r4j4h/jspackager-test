jspackager-test
===============

About
------------

PHP based annotation parser and dependency resolver.

JsPackager is based around three core pieces:

- Recursive Annotation Based Declared Dependency Resolver supporting Packages and Remote files for basic CDN support
  - Takes a source file's file path and returns an array containing one to many file paths pointing to that file
  and its dependencies.
- Compiler
  - Uses the output of the Dependency Resolver to determine compilable files, passing the compilable ones through
   Google's Closure Compiler and writing a manifest file to enable re-stitching the payload with the
   non-compilable files together later.
- Manifest Parser
  - Takes a source file's file path and returns an array containing one to many file paths pointing to that file
   and its dependencies.
  - It differs from the Dependency Resolver in that it tries to do the resolution with minimal parsing effort by using
  the manifests written by the Compiler and the compiled files and any non-compilable source files.

Being primarily used for the web, file pathing can become a complex issue:

File Paths in Play
-------------

- Filesystem Absolute - Path to get to file on HD
  - Inaccessible to browers
  - Not portable

- Relative to project root - Most typical
  - Inaccessible to browsers
  - Portable

- Browser relative - Generally a www or public subfolder located in the project root
  - Accessible to browsers
  - Portable

- Remote - Generally a subfolder located inside www or public that is also present at another URL
  - Accessible to browsers
  - Converted into an @remote annotation, and later expanded out to the target URL.

Generally, output will always consist of Browser relative and Remote paths.

For compilation, Filesystem Absolute is used, but this is generally internally only.

For input, relative to project root is usually used but sometimes it is browser relative.
A danger here is that Browser relative can be misinterpreted as Filesystem Absolute. Thus, you may need to hint
the type of path for proper operation.



Projects Using
------------

- [r4j4h/jspackager-cli](https://github.com/r4j4h/jspackager-cli)
  - A Symfony based Command Line Interface
- [r4j4h/jspackager-zf2](https://github.com/r4j4h/jspackager-zf2)
  - Zend 2 View Helpers utilizing r4j4h/jspackager-test
