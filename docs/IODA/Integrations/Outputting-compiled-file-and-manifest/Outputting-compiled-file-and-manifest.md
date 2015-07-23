Self-contained IODA

- Integrations
    - Output paths for either normal files or compiled files depending on mode
- Operations
- Data
    - [File](../Data/File.md)


- Apis

# Considerations:
    - If we deal with just file paths, we cannot support fragments of content. To support that, how manifests/compiled files
 are handled for fragments must be determined. Additionally, passing fragments through DependencyResolver must be made
 possible and then utilized. One way is to write fragments out to temporary files.