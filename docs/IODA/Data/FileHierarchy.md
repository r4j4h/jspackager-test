Represents a dependent File that depends on another File or DependentFile. Thus while Files are always root nodes and can be leaves on their own, DependentFiles
are always leaves ultimately pointing back to some File.

Consideration:
Is it worth separating this concept from File itself?
Yes. File encases all validation for a valid virtual File, whether path or content based.
DependentFile represents a CompositeFile basically, and is in charge of validating that dependencies point to a root File
and that they are maintained in order as far as the source and final code is concerned.

This is responsible for maintaining the hierarchy then, with each node being in charge of maintaining it's own individual integrity.

[]{type:'unknown'|'script'|'style'|'etc',content:'some/file.js'}
The order is handled by the array

Someone still needs to run through the list to filter types, but this way we don't have to have an individual mapping
that correlates individual buckets. If we want to keep the individual mapping with buckets, at the very least we need
to have a more flexible bucketing strategy, so that new ones can be easily added and consumers can easily read from
or ignored.

I think this one array is simpler and provides just as many benefits. The biggest case will be running through once
and making your own buckets from stuff you are interested in and then using those buckets in further processing.

This should be abstracted away either way from consumers/users of this data by a function should be provided that
returns the dependencies, in order. Whether in File representation, file paths/content blocks, or HTML tags.

File Hierarchy
    Tracks a breadth-first ordered tree of File and FileFragment objects


File Hierarchy Transformer/Parser
    Translates a File Heirarchy and set of File and FileFragments into a flat, ordered array of dependencies (file paths and blocks of content for content blocks?)
        Used by compiler, concats file paths and content blocks and passes to Processors? No that'd lose sourcemapping..
        Used by compiler, prints blocks of contents to tmp files and passes ordered array of file paths to Processors
            1. bar.js, { function() .... }, baz.js
            2. { function() .... } -> /tmp/foo.js
            3. bar.js, foo.js, baz.js
    Translates a File Heirarchy and set of File and FileFragments into a flat, ordered array of html tags (script src, src>contents, style hrefs, etc)
            1. bar.js, { function() .... }, baz.js
            2. <script src="bar.js, <script>function() ....., <script src="baz.js


