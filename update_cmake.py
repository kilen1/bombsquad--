#!/usr/bin/env python3

"""
Script to add server_host feature set files to CMakeLists.txt
"""

import os

def add_server_host_files_to_cmake():
    cmake_path = "/workspace/движок/ballistica-main/ballisticakit-cmake/CMakeLists.txt"
    
    # Read the current CMakeLists.txt
    with open(cmake_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Define the files to add
    server_host_files = [
        "${BA_SRC_ROOT}/ballistica/server_host/python/methods/python_methods_server_host.cc",
        "${BA_SRC_ROOT}/ballistica/server_host/python/methods/python_methods_server_host.h",
        "${BA_SRC_ROOT}/ballistica/server_host/python/server_host_python.cc",
        "${BA_SRC_ROOT}/ballistica/server_host/python/server_host_python.h",
        "${BA_SRC_ROOT}/ballistica/server_host/server_host.cc",
        "${BA_SRC_ROOT}/ballistica/server_host/server_host.h"
    ]
    
    # Find the insertion point (after the template_fs files and before ui_v1 files)
    insertion_marker = "${BA_SRC_ROOT}/ballistica/template_fs/template_fs.h"
    insertion_index = content.find(insertion_marker)
    
    if insertion_index != -1:
        # Find the end of the line containing the marker
        insertion_point = content.find('\n', insertion_index)
        if insertion_point != -1:
            # Add our files after the insertion point
            files_text = '\n  '.join(server_host_files)
            new_content = content[:insertion_point + 1] + '  ' + files_text + '\n' + content[insertion_point + 1:]
            
            # Write the updated content back to the file
            with open(cmake_path, 'w', encoding='utf-8') as f:
                f.write(new_content)
            
            print("Successfully added server_host files to CMakeLists.txt")
        else:
            print("Error: Could not find proper insertion point in CMakeLists.txt")
    else:
        print("Error: Could not find insertion marker in CMakeLists.txt")

if __name__ == "__main__":
    add_server_host_files_to_cmake()