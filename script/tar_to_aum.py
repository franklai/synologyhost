import json
import os
import shutil
import sys

extension_name = 'host'
module_root = '../modules'
archive_root = '../archive'
include_root = '../include'
include_dir = 'include'
info_file = 'INFO'
common_file = 'fujirou_common.php'


def do_tar(module_name):
    module_dir = os.path.join(module_root, module_name)
    info_path = os.path.join(module_dir, info_file)
    include_temp = os.path.join(module_dir, include_dir)

    if not os.path.exists(info_path):
        return False

    text = open(info_path, 'rb').read()
    obj = json.loads(text)

    # key exists check
    if 'module' not in obj or 'version' not in obj:
        return False

    module_dir = os.path.join(module_root, module_name)
    module_file = obj['module']
    php_path = os.path.join(module_dir, module_file)

    cmd = 'ls %s %s' % (info_path, php_path)
    os.system(cmd)

    module_version = obj['version']
    archive_file = '%s-%s.%s' % (module_name, module_version, extension_name)
    archive_path = os.path.join(archive_root, archive_file)
    
    files_to_be_archived = [info_file, module_file]

    # copy include dir to current path
    include_exists = os.path.exists(include_root)
    if include_exists:
        files_to_be_archived.append(include_dir)

        shutil.copytree(include_root, include_temp)

        cmd = 'chown -R root:root %s' % (' '.join(files_to_be_archived))
        os.system(cmd)

    cmd = 'COPYFILE_DISABLE=1 tar zcvf %s -C %s %s' % (archive_path, module_dir, ' '.join(files_to_be_archived))
    os.system(cmd)

    if include_temp:
        shutil.rmtree(include_temp)

def print_usage():
    print('%s [module_name]\n' % (os.path.basename(__file__)))

    sys.exit(0)

if __name__ == '__main__':
    argv = sys.argv

    if len(argv) < 2:
        print_usage()

    module_name = os.path.basename(os.path.normpath(argv[1]))

    do_tar(module_name)
