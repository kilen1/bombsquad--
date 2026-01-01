/* This file is part of the Ballistica project. */
/* See the COPYING file at the top-level directory of this distribution. */

#include "ballistica/server_host/python/methods/python_methods_server_host.h"

#include <vector>

#include "ballistica/core/core.h"
#include "ballistica/shared/python/python_macros.h"
#include "ballistica/server_host/server_host.h"

namespace ballistica::server_host {

// -------------------------- get_host_name --------------------------------

static auto PyGetHostName(PyObject* self, PyObject* args) -> PyObject* {
  BA_PYTHON_TRY;
  std::string host_name = ServerHost::GetHostName();
  return python::PyUnicode_FromString(host_name.c_str());
  BA_PYTHON_CATCH;
}

static PyMethodDef PyGetHostNameDef = {
    "get_host_name",              // name
    (PyCFunction)PyGetHostName,   // method
    METH_NOARGS,                  // flags
    "get_host_name() -> str\n"
    "\n"
    "Get the current server host name."};

// -------------------------- set_host_name --------------------------------

static auto PySetHostName(PyObject* self, PyObject* args) -> PyObject* {
  BA_PYTHON_TRY;
  const char* name;
  if (!PyArg_ParseTuple(args, "s", &name)) {
    return nullptr;
  }
  ServerHost::SetHostName(std::string(name));
  Py_RETURN_NONE;
  BA_PYTHON_CATCH;
}

static PyMethodDef PySetHostNameDef = {
    "set_host_name",              // name
    (PyCFunction)PySetHostName,   // method
    METH_VARARGS,                 // flags
    "set_host_name(name: str) -> None\n"
    "\n"
    "Set the server host name."};

// -------------------------- get_default_host_name --------------------------------

static auto PyGetDefaultHostName(PyObject* self, PyObject* args) -> PyObject* {
  BA_PYTHON_TRY;
  std::string default_host_name = ServerHost::GetDefaultHostName();
  return python::PyUnicode_FromString(default_host_name.c_str());
  BA_PYTHON_CATCH;
}

static PyMethodDef PyGetDefaultHostNameDef = {
    "get_default_host_name",              // name
    (PyCFunction)PyGetDefaultHostName,    // method
    METH_NOARGS,                          // flags
    "get_default_host_name() -> str\n"
    "\n"
    "Get the default server host name."};

// -----------------------------------------------------------------------------

auto PythonMethodsServerHost::GetMethods() -> std::vector<PyMethodDef> {
  return {PyGetHostNameDef, PySetHostNameDef, PyGetDefaultHostNameDef};
}

}  // namespace ballistica::server_host