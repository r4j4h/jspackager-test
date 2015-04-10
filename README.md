jspackager-test
===============

About
------------

JsPackager is an annotation-based dependency resolver intended for the front end. As developers it is easier to work
in multiple files, but for browsers we need them packaged together.

JsPackager allows you to pull in complex dependency tree and have it resolve down to something manageable.

It supports having a remote path for files, as opposed to the local workspace. [how do we describe this better?]
This remote path can be stored locally for development, and on a CDN in production, and the remote path can be
changed out.



Want jquery? Gotta patch it with something? Want that other jquery plugin? Want to treat all of those as one dependency?
Or do you want to only pull in the parts you use where you use them?
Do you want all of that in one bundle or in several smaller bundles?

All of these are possible with JsPackager and some combination of @require and @root annotations.


Motivation
------

Web pages are powered by script and link tags which tell browsers which stylesheets and javascript files to download and execute.

Maintaining these tags is a pain. If we want to re-use something, everything has to mention it everywhere it is used.

```html
<link rel="stylesheet" href="serious-app-styles.css" type="text/css">
<script src="vendor/jquery.js.js" type="text/javascript"></script>
<script src="lib/dialog.js" type="text/javascript"></script>
<link rel="stylesheet" href="login-specific.css" type="text/css">
<script src="serious-app-login.js" type="text/javascript"></script>
```
```html
<link rel="stylesheet" href="serious-app-styles.css" type="text/css">
<script src="vendor/jquery.js.js" type="text/javascript"></script>
<script src="lib/dialog.js" type="text/javascript"></script>
<link rel="stylesheet" href="dashboard-specific.css" type="text/css">
<script src="serious-app-dashboard.js" type="text/javascript"></script>
```

JsPackager is a tool to help with this. It acts as a file dependency resolver, and a little more. It is comprised of

- a Dependency Resolver
  - Takes a file and resolves it into a set of dependencies, generally consumed as an ordered array of file paths
  - Provided is an annotation resolver
  - Planned is re-use of existing CommonJS parsers, both using regexp and ASTs
    - Planned support for optional async loading
- a Packager
  - Takes a set of dependencies and converts into a format for later speedy use
  - Provided is a compiled file and manifest file driven packager
  - Planned is a Grunt Config driven packager
- a Processor
  - Takes an ordered set of file paths and runs them through some form of processing
  - Provided is a Google Closure Compiler processor supporting local .JAR or API use
  - Planned is css and asset processing (sass, jpegtrans, etc)

Not included but available in sister repositories:
- a View Helper
  - Helps convert the file paths into script/link tags suitable for direct inclusion on a page
  - Provided is a Zend 1 View Helper, Zend 2 View Helper, and a Node.js wrapper
- a Command Line Interface
  - Helps any language resolve files and get the file paths back
  - Provides an easy way to compile files


<overview>

The default resolver/packager moves the dependencies into the file itself in the form of annotations








.




Why Not Just Use <x>?
-------------

There are several technologies around that take different approaches and provide different aspects,
 ranging from require.js to ES6 Modules to Polymer to many others.

JsPackager is closer to Microsoft's TypeScript's annotations than it is to require.js. It is also designed to happen
on the server side rather than pushing things to the client.

It grew out of necessity and already existing code, bending to the desires of the developers around it as it has grown.


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
