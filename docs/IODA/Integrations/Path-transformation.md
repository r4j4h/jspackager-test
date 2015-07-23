Self-contained IODA

- Integrations
    - Transform
- Operations
    - Transform Web Path to relative path
        - Data In
            - Web Path
            - Web Root
        - Data Out
            - Relative Path
    - Transform Web Path to Absolute Path
        - Data In
            - Web Path
            - Web Root
            - Local Root Path
        - Data Out
            - Absolute Path
    - Transform Absolute Path to relative path
        - Data In
            - Absolute Path
            - Local Root Path
        - Data Out
            - Relative Path
    - Transform Absolute Path to Web Path
        - Data In
            - Absolute Path
            - Local Root Path
            - Web Root
        - Data Out
            - Web Path
    - Transform a relative path to Absolute Path
        - Data In
            - Relative Path
            - Local Root Path
        - Data Out
            - Absolute Path
    - Transform a relative path to web based path
        - Data In
            - Relative Path
            - Web Root
        - Data Out
            - Web based Path
- Data
    - Web Path
        http://www.foo.net/bar/baz.php
        @remote/bar/baz.php
    - Web Root
        http://www.foo.net
    - Local Root Path
        C:/sites/foonet
        /www/foonet
        /Users/sites/foonet
    - Relative Path
        bar/baz.php
        ./bar/baz.php
    - Absolute Path
        C:/sites/foonet/bar/baz.php
        /www/foonet/bar/baz.php
        /Users/sites/foonet/bar/baz.php
- Apis

# Considerations:
    - If we deal with just file paths, we cannot support fragments of content. To support that, how manifests/compiled files
 are handled for fragments must be determined. Additionally, passing fragments through DependencyResolver must be made
 possible and then utilized.