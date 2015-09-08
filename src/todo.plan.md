# .plan

  Quick value I want to get done is cleani reslver pipeline and process pipeline

      for loading in files and compiling:

        we send file paths into resolver pipeline which with a AnnotationBasedFileResolver which turns them into
         DependencyFileInterface's with metadata pointing at the source files and dependencies
         // todo lets move DependencyFileInterfaceToDependencySetProccessor to happen at beginning of system and only use dependency sets?
         // todo this works as long as dependencysets maintain original files properly
         // and can happen again inbetween as it currently is, if needed?
        we send those guys into the process pipeline with a DependencyFileInterfaceToDependencySetProccessor which turns them into DependencySets and then pases to CompiledAndManifestProcessor
    // todo Refactor out dependencysets to just use raw DependencyFileInterface's with metadata

    what we really need is a reliable, safe integrity maintaining way to convert between modalities:

        Nested File trees

                Redundant includes are a situation where these suffer in performance
                    For example, let's say we have main and it requires foo and bar and both of those require baz.
                    We would include baz twice! It would get filtered out in the unique'ing process but still remains.
                    A processor must work on it twice, or replicate it twice, lest the dependency's get hidden.

                    In comparison to array of arrays, array of arrays essentially normalizes this out for us. We need
                    that along with the metadata. Then we're golden.


        Array of Arrays of Files
            Should include the metadata of the Nested file trees where relevant


        DependencySet representing an ordered list of Files (does not support fragments!)
            FileToDependencySetsService's getDependencySets()

        Summary

               Array of Arrays of Files is the best medium.
                Nested File trees expose redundant nodes, while Array of Arrays of Files compress them where possible.
                DependencySets reduce down to paths and lose any metadata or ability to support fragments, while
                Array of Arrays of Files mimics the overall structuring while maintaining composition of File elements.



    Nested File Trees break up at the point of recursion, when parsing nested file trees
     is the point at which a new sub-array is started going right-to-left in the Array of arrays of Files. Accordingly,
     it is a DependencySet representing this new recursed set prepended to the current DependencySetCollection.



        CompiledAndManifestProcessor transforms dependency sets into a manifeset files contents and minifies the contents
            // or DependencyFileInterfaceToDependencySetProccessor could go inside here and leave outside as the tree root file
        The procesor pipeline then hands to the outputter pipeline
        outputter pipeline hands to a file outputter which writes out the manifest files and the minified files
        and hands the outputer results back to the callerr

      no that went from dev to compiled lol for dev mode from view helper we:

        we send file paths into resolver pipeline which turns them into DependencyFileInterface's with metadata
        we send those guys into the process pipeline with a CacheBustProcessor
        CacheBustProcessor takes DependencyFileInterface's with metadata and adds cache busts at end of filenames
        The procesor pipeline then hands to the outputter pipeline
        the outputter pipeline then hands DependencyFileInterface's with metadata to a HTML outputter which writes out the
         DependencyFileInterface's with metadata - as link tags if files are within webroot path or inline scripts if they aren't
        and hands the outputter results back to the caller

      and lastly for using those compiled files from view helper we:

        we send file paths into resolver pipeline with a CompiledAndManifestResolver which turns them into
         DependencyFileInterface's with metadata pointing at the compiled files and manifest files
        we send those guys into the process pipeline with a CacheBustProcessor
        CacheBustProcessor takes DependencyFileInterface's with metadata and adds cache busts at end of filenames
        The procesor pipeline then hands to the outputter pipeline
        the outputter pipeline then hands DependencyFileInterface's with metadata to a HTML outputter which writes out the
         DependencyFileInterface's with metadata - as link tags if files are within webroot path or inline scripts if they aren't
        and hands the outputter results back to the caller

Thus tracing the flow

    file paths
      RESOLVER PIPELINE
    DependencyFileInterface's with metadata
    DependencySets
      PROCESSING PIPELINE
    ProcessingResult
      OUTPUTTER PIPELINE
    OutputterResult

todo current processingresult is what outputterresult should be
 todo procesingresult should just be more dependencyfileinterfaace's with metadata - potentially modified

 todo DependencyTree should ALL go within annotations and not be used by CompiledAndManifest if lucidchart is correct!
 todo and right now IT is instantiating AnnotationBasedResolver!

 todo IDENTIFY WHO SHOULD CREATE THE RESOLVER PIPELINE AND WHO SHOULD FILL IT WITH ANNOTATIONBASEDRESOLVER WHICH SHOULD UTILIZE DEPTREE/DEPTREEPARSER
 todo IDENTIFY WOH SHOULD CREATE THE RESOLVER PIPELINE AND WHO SHOULD FILL IT WITH COMPILEDANDMANIFESTRESOLVER
 TODO IDENTIFY WHERE COMPILER SHOULD CREATE THE RESOLVER PIPELINE AND FILL IT WITH ANNOTATIONBASEDRESOLVER WHICH SHOULD UTILIZE DEPTREE/DEPTREEPARSER

- [x] Convert things over to using metaData arrays
- [x] Enable use of Streaming / Content based files
- [ ] Incorporate path/streaming/content pointing to original file until altered

- [ ] Implement SimpleOutputterResult based on nomnomnom thing
- [ ] Move stuff out of Compiler into CompiledAndManifestProcessor

- [ ] Clean up so each high level domain is represented form [The arch diagram](https://www.lucidchart.com/documents/edit/56ac0501-793a-439e-bdac-6f0d32d8cb66/0)
  - [x] dependencytree should go into resolver
  - [x] pathfinder should go into Helpers
  - [x] constants should go into Helpers
  - [x] FileHandler should go into Helpers
  - [ ] the shared parts of manifestcontentsgenerator and manifestresovler should be extracted into a shared library so that changes are replicated to both and understood. namely the manifest format
    - [x] review manifestcontentsgenerator and manifestresovler
    - [ ] identify shared parts of manifestcontentsgenerator and manifestresovler
    - [ ] extract shared parts into a shared library
  - [ ] manifestcontentsgenerator should go into processor
  - [ ] manifestresolver should go into resolver

- DependencyTree is now revealing itself to be a simple container
- DependencyTreeParser is now revealing itself to be an integration
- AnnotationBasedResolverContext holds some of this data, like mutingMissingFileExceptions which can be shared around
  - Should individual contexts for each area be made? Wherever it makes sense in a domain driven design perspective



- [ ] provide a file finder that enables "@root used as token for finding root files to become compiled into a .compiled version"
  - I can implement this by making a compound/composite-resolver that uses annotationbasedfileresolver?
  - [ ] use resolver on all files found ind directory, only caring about @root annotations, building them into a list
    - filter / reduce
    - it uses the existing annotation resolver without recursing (that option may need to be added!) which returns an array of Files
    - it returns all Files with isRoot == true
  - [ ] pass each file in that list to normal compilation process
    - compilation processor? or simple closure function that re-uses other stuff inside the compiler function? we just provide the resolver


- [ ] DependencyTreeParser is coupled to AnnotationResponseHandler via action/annotationIndex pair
  - [ ] Define a consistent FileResolverInterface return/spec/DAO object -- right now we are just returning the File proper
  - [x] Refactor AnnotationResponseHandler
  - [ ] Test AnnotationResponseHandler
  - [x] Refactor FileResolverInterface's resolveDependenciesForFile to maybe take a File object to support files and fragments and re-use the already parsed File object - also gets a Context parameter
  - [x] Refactor DependencyTreeParser to not be coupled to annotations - annotation detect/skip logic should move into the annotation resolver
  - [ ] Refactor AnnotationResponseHandler's root annotation handling logic for setting root files, etc should be part of the File/DependencyFile API Data object
    - It is, already, by setting file property on file object?? or did i mean a setter function? Maybe see FileQualia
    - [ ] What would it take to allow annotations to determine where bundles start/stop? line start/end numbers?
    - [ ] Provides room for supporting other bundling
      - [ ] Make a webpack bundler that sends things to webpack and wraps the webpacked bundles as Files with root flag set to true
    - [ ] Annotation naming e.g. @remote gets separated from naming of actual mechanism, e.g. remote/root - perhaps @root is called doNotBundle boolean or something in model
    - [x] Refactor translateAnnotationsResponseIntoExistingFile to not be annotation hardcoded-key specific


- [ ] File needs to be reviewed in respect to FileFragments
  - [ ] Decoupling from Annotations
    - Create FileQualia new object for tracking qualia about Files that were previously tracked via annotations
    - File has Annotationordermap
      - should use FileQualia so not coupled to annotations
      - isn't array of qualia == array of annotations? itc would metadata or metaDataMap be a better name?
    - DependencyTreeParser uses 'root' and 'nocompile' annotations for variables and iterates the annotations to hand to AnnotationHandler
      - [ ] root/nocompile variable logic should pull from FileQualia, it pulling from file is kind of the same?
      - [x] iterating annotations to AnnotationHandler should happen within the Annotation Resolver to convert to FileQualia

- [ ] Compiler is dependent on manifests and outputting files
  - [ ] Manifests should be wrapped in their own CompiledAndManifestProcessor - taking in a File and creating a new File with changed, compiled contents and manifests added to its metadata
  - [ ] Outputting should be wrapped in a CompiledAndManifestOutputter - taking in a File and printing out its contents to a file and printing out a manifest if one is in its metadata
  - [ ] Define Data format that is passed to Processor
  - [ ] Define Data format that a Processor returns
  - [ ] Define Data format that is passed to Outputter
    - Array of file paths
      - Does not support fragments, etc, so we should pass array of filepath/fragment objects
  - [ ] Define Data format that a Outputter returns
    - Status
    - Reference to output file?
    - Reference to output file's contents?
    - Reference to source file

- [ ] DependencyTree uses FileFlatteningService to handle ALL parsing
  - [x] Service should be reviewed for renaming or splitting into a new interface or something
  - DependencyTree uses it to flatten a File into its dependencies
  - Service needs to be able to do this without being coupled to annotations
  - Annotation specific things need to be moved elsewhere
  - Perhaps there is a File processor that reads the annotation metadata and hydrates things from that
  - $respectingRootPackages should be moved to a Context object
  - Service is returning an array of scripts and array of stylesheets
    - Should return an array of generic files that may have metadata  <----!!!!!!!
    - Interested parties can filter it for ones they support and utilize metadata to include/ignore sourcemaps, manifests, etc.
    - Need to define that Data object

- [ ] RemoteAnnotationPathingService - handles @remote annotation detection/parsing, and replacing with paths
  - Compiler expands out @remote annotations via RemoteAnnotationStringService
    - could use RemoteAnnotationPathingService
  - ManfiestContentsGenerator can detect @remote annotations via RemoteAnnotationStringService
    - could use RemoteAnnotationPathingService

- [ ] Use streams
    - [ ] Determine if streams would alleviate slow running tests
    - [ ] File reading should be converted to use streams
    - [ ] File writing should be converted to use streams
    - [ ] File processing should be converted to use streams

- [ ] Replace the identifier cache with an SplObjectStorage that stores a set of File objects?
  - Might actually be risky if two separate Files can be generated for the same File... so lets not do this
