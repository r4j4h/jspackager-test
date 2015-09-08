Describing @Annotation DependencyResolver with CompiledFileAndManifest DependencyResolver

2 phase packaging system:

Run Phase
  - @Annotation Dependency Resolver
  - CompiledFileAndManifest Dependency Resolver
  - Bower Dependency Resolver
  - Dependency Resolver Logic Switch
  - Cache Bust Processor
  - Tagger Outputter

Packaging Phase
  - @Annotation Dependency Resolver
  - Bower Dependency Resolver
  - GCC Processor
  - CompiledFileAndManifest Outputter
