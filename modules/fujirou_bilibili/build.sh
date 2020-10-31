#!/bin/sh

if [ -f "/bin/tar" ]; then
TAR=/bin/tar
fi
if [ -f "/usr/bin/tar" ]; then
TAR=/usr/bin/tar
fi
PHP=/usr/bin/php
FILES="INFO common.php host.php"

GetNameAndVersion()
{
    local phpSrc="
        \$json = json_decode(file_get_contents(\"INFO\"), TRUE);
        if (\$json)  {
            echo \$json[\"name\"] .'-'. \$json[\"version\"];
        } else {
            printf(\"Failed to json decode INFO\\n\");
        }
    "

    local cmd=""
    local nameAndVersion=$($PHP -d open_basedir= -r "$phpSrc")
    echo $nameAndVersion
}

nameAndVersion=$(GetNameAndVersion)

hostFile="$nameAndVersion.host"

echo "Create $hostFile contains [$FILES]"
$TAR zcf $hostFile  $FILES


# vim: expandtab ff=unix ts=4 

