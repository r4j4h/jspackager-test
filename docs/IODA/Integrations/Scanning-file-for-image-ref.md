- Data In
    - File (path OR contents)
- Data Out
    - []File (contents)


Self-contained IODA

- Integrations
    - Handling an annotation
    - Converting annotations to things on page
- Operations
    - Collecting image references by detecting a file path in a section of content
        - Data In
            - File
        - Data Out
            - []CompressibleImageAnnotation
    - Changing image references in a file
        - Data In
            - [File, []CompressibleImageAnnotation]
        - Data Out
            - File (paths or contents)
- Data
    - CompressibleImageAnnotation
        - Ex: <img src="<path>"></img>
            - Tag: ImageSrc
            - Params: <path>
            - Notes: Uses AST parsers rather than regexp for safety/reliability?
            - Otherwise ick, Multiline? Weird formatted HTML? Ick.
            - Transforms contents and outputs a virtual content-based File with the changes
                - Can be written or used
    - [File](../Data/File.md)
- Apis
