/* This file is part of the Ballistica project. */
/* See the COPYING file at the top-level directory of this distribution. */

#include "ballistica/server_host/python/server_host_python.h"

#include "ballistica/server_host/server_host.h"
#include "ballistica/shared/python/python_object_set.h"
#include "ballistica/server_host/python/class/python_class_server_host.h"
#include "ballistica/server_host/python/methods/python_methods_server_host.h"

namespace ballistica::server_host {

void ServerHostPython::AddPythonClasses(PyObject* module) {
  // Add any Python classes we have
  // PythonModuleBuilder::AddClass<PythonClassServerHost>(module);
}

void ServerHostPython::ImportPythonObjs() {
  // Import any Python objects we need
}

}  // namespace ballistica::server_host