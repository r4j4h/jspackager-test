Feature: File resolution using compiled and manifest files.

  In order to compile my files and pull them onto a page with a consistent interface
  As a developer
  I want to hand my source file path to a resolver to get an ordered list of
  dependent files using only a compiled file and its manifest.

  Scenario: Resolving files from a compiled file and its manifest.
    Given a source file named "source.js"
    And a compiled file named "source.compiled.js"
    And a manifest file named "source.js.manifest" containing
    """
    compiled.css
    dep_a.js
    """
    When I resolve for the source file
    Then display last command output:
    """
    behattestdir/compiled.css
    behattestdir/dep_a.js
    behattestdir/source.compiled.js
    """

  Scenario: Resolving files from a compiled file with no manifest.
    Given a source file named "source.js"
    And a compiled file named "source.compiled.js"
    When I resolve for the source file
    Then display last command output:
    """
    behattestdir/source.compiled.js
    """

  Scenario: Resolving files from a manifest missing its compiled file.
    Given a source file named "source.js"
    And a manifest file named "source.js.manifest" containing
    """
    compiled.css
    dep_a.js
    """
    When I resolve for the source file
    Then I get an exception:
    """
    Compiled file "behattestdir/source.compiled.js" is missing!
    """

  Scenario: Resolving files from a missing file.
    Given a source file named "source.js"
    When I resolve for the source file
    Then I get an exception:
    """
    Compiled file "behattestdir/source.compiled.js" is missing!
    """
