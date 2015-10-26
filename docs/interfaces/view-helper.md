Provides an API to create a collection of ordered filenames to send to a Dependency Resolver to resolve into a new collection of ordered filenames and hands that to a Tagger to be prepared for presentation on the page.

Tagger is a transform on a set of filenames... worth abstracting out to be used elsewhere?

-----

The following example illustrates one way the current application config-driven behavior can be re-created in this manner:


$cfg = new Configurator();
$cfg->basePath = ::getBasePath();
$cfg->remoteUrl = '/shared';
if ( $conigSaysUseCompiled )
    $cfg->resolverFactory = function() {
        return new CompiledManifestResolver('compiled.js', 'js.manifest', '@remote', 'shared/');
    }
} else {
    $cfg->resolverFactory = function() {
        return new AnnotationBasedResolver(array(
            'require' => new RequireAnnotationHandler(),
            'requireRemote' => new RequireRemoteAnnotationHandler('@remote', 'shared/'),
            'requireStyle' => new RequireStyleAnnotationHandler(),
            'requireRemoteStyle' => new RequireRemoteStyleAnnotationHandler(),
            'root' => new RootAnnotationHandler(),
            'noCompile' => new NoCompileAnnotationHandler()
        ));
    }
}

$vh = new ViewHelper($cfg);
$vh->add('somefile.js');
$vh->add('anotherfile.js');
$vh->getStylesheets();
$vh->getScripts();

-----------

The following example illustrates how one can use the current strategy and additionally load files referenced in a Bowerfile.

<do example, should use compositeAndResolver and feed it the two. The composite would be in charge of knowing or caring
if either resolve swallows adds and only prints on the output like the bower processor.

Or if we pipeline can they all do it? Then we might get redundant adds if two people pass through without swallowing.
Or does each one get a chance and the first one that says it can does?

We need a pipeline where each gets asked if they can handle adding the file and the first one that says yes gets it


-----------

Event storm around using other things with view helper

include moment via npm
    want to auto include moment from npm install
        // @requireNpm moment
        // causes package.json and node_modules folder to be searched
include moment via bower
    want to auto include moment from bower install
        1.
            // @requireBower moment
            // causes bower.json to be read and moment extracted from it
        2.
            auto read bower.json and include anything in it?
                seems frought with peril but we can use wiredep to do this!
                make a wiredep-raw-cli wrapper that just runs wiredep and returns the js/css/etc json output voa stdout
                have jspackager wiredep component use wiredep-raw-cli to read bower.json and get the things in order
                    then it adds those files as dependencies
include riot tags
    want to write .tags and have them compiled into raw js and included as part of core
        1.
            // @requireRiotTag foo.tag
            // @requireRiotTag bar.js // Should support already compiled riot stuff too
        2. runs through riot compiler and gets file contents if needed, or uses file contents as is
        3. adds file contents to page


Are those involved or needed during compile step? Should they be?
    Yes, this would obviate the need for npm/bower/riot on the end system, allowing them to just take the build files.
    Do you see any problems with involving those off the bat?
        Well, yes.
        npm brings in the potential of including node-specific, non-browser-friendly packages.
            Is there a way to prevent against that?
        I don't like the idea of combining everything but this isn't for release it's for production use.
            E.g. a crossfilter library requires crossfilter to also be included but does not combine it.
        Bower through wiredep ensconces bower versions at a given point in time to the built package, instead of allowing
        it be a necessary dependency by the end user. Wiredep provides excludes as an option. JsPackager actually does not!
        Lastly, riot tags may have issues with IE8 or IE9 and may collude them without people being informed.
Are those really related to JsPackager or should they be separate things integrate-able with it?
    Yes and no. Due to being incorporated JsPackager must support the extensibility points they need, but it should
    remain more flexible to incorporate other things as well, and thus not entirely coupled to these particulars.
