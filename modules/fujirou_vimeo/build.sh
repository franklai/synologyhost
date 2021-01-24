#!/bin/sh

TAR=/bin/tar
PHP=/usr/bin/php
HOST=vimeo
FILES="INFO common.php host.php"

GetVersion()
{
    local phpSrc="
        \$json = json_decode(file_get_contents(\"INFO\"), TRUE);
        if (\$json)  {
            echo \$json[\"version\"];
        } else {
            printf(\"Failed to json decode INFO\\n\");
        }
    "

    local cmd=""
    local version=$($PHP -d open_basedir= -r "$phpSrc")
    echo $version
}

hostVersion=$(GetVersion)

hostFile="$HOST-$hostVersion.host"

echo "Create $hostFile contains [$FILES]"
$TAR zcf $hostFile  $FILES


# vim: expandtab ff=unix ts=4 

