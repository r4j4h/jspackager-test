Given configuration of what annotations to look for and how to handle them
When resolving a filename
then it parses the file line by line searching for annotations which are extracted into a list of dependencies and parses each dependency in this way recursively
and it finishes this process with a set of file paths each with some metadata annotations.