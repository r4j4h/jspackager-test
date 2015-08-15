# Bounded Contexts
##### [README](README.md) > [Docs](docs/) > Bounded Contexts

This project can be viewed in a Domain Driven Design way, where it breaks into the following
Bounded Contexts and relationships:

- [View Domain](#view-domain)
- [CLI Domain](#cli-domain)
- [Dependency Resolution Domain](#dependency-resolution-domain)
- [Compilation Domain](#compilation-domain)
- [HTML Generator Domain](#html-generator-domain)

![Bounded Context Overview Image](media/docs/jspackager--bounded-contexts.png)

## View Domain

Responsible for taking a file or a list of files and pairing them with any necessary metadata and passing them
through the Dependency Resolution Domain and handing the result to the HTML Generator Domain and returning
the final result for inclusion on a page.

## CLI Domain

Responsible for taking a file or a list of files or a folder or list of folders and pairing them
with any necessary metadata and passing them through the Dependency Resolution Domain and handing the result to the
Compilation Domain, returning not only the final result but notifications of progress through the Domain.

## Dependency Resolution Domain

Responsible for taking a file and potential metadata (or a list of files and (metadata)) and returning an ordered list
including the ones given and any files they require. This includes resolving annotation-based requires, baked files
representing dependencies, and parsing bower.json files and using wiredep to resolve bower-imported dependencies.

## Compilation Domain

Responsible for taking a list of files and potential metadata and processing them in some way: such as concatenation and minification, baking
a file that represents dependencies, detect local urls and process their contents and modify the urls to point
to processed contents, or lint and generate reports.


## HTML Generator Domain

Responsible for taking a list of files and returning them as valid HTML ready for inclusion on a page.
