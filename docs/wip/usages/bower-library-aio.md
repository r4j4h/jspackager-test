Describing @Annotation DependencyResolver with CompiledFileAndManifest DependencyResolver

2 phase packaging system:

Run Phase
  - Bower Dependency Resolver
  - CompiledFile Dependency Resolver
  - Dependency Resolver Logic Switch
  - Cache Bust Processor
  - Tagger Outputter

Packaging Phase
  - Bower Dependency Resolver
  - GCC Processor
  - CompiledFile Outputter
