// Released under the MIT License. See LICENSE for details.
//
// Methods related to server hostname.

#include "ballistica/scene_v1/python/methods/python_methods_server_hostname.h"

#include "ballistica/base/python/support/python_context_call.h"
#include "ballistica/classic/support/classic_app_mode.h"
#include "ballistica/core/python/core_python.h"
#include "ballistica/scene_v1/python/scene_v1_python.h"

namespace ballistica::scene_v1 {

static auto PySetServerHostname(PyObject* self, PyObject* args, PyObject* keywds)
    -> PyObject* {
  BA_PYTHON_TRY;
  const char* hostname;
  static const char* kwlist[] = {"hostname", nullptr};
  if (!PyArg_ParseTupleAndKeywords(args, keywds, "s", 
                                   const_cast<char**>(kwlist), &hostname)) {
    return nullptr;
  }
  
  // Store the hostname in the app config or a global variable
  auto* appmode = classic::ClassicAppMode::GetActiveOrThrow();
  appmode->set_server_hostname(std::string(hostname));
  
  Py_RETURN_NONE;
  BA_PYTHON_CATCH;
}

static auto PyGetServerHostname(PyObject* self, PyObject* args, PyObject* keywds)
    -> PyObject* {
  BA_PYTHON_TRY;
  static const char* kwlist[] = {nullptr};
  if (!PyArg_ParseTupleAndKeywords(args, keywds, "", 
                                   const_cast<char**>(kwlist))) {
    return nullptr;
  }
  
  auto* appmode = classic::ClassicAppMode::GetActiveOrThrow();
  std::string hostname = appmode->server_hostname();
  
  return PyUnicode_FromString(hostname.c_str());
  BA_PYTHON_CATCH;
}

static PyMethodDef PySetServerHostnameDef = {
    "set_server_hostname",                  // name
    (PyCFunction)PySetServerHostname,       // method
    METH_VARARGS | METH_KEYWORDS,           // flags
    "set_server_hostname(hostname: str) -> None\n"
    "\n"
    "(internal)",
};

static PyMethodDef PyGetServerHostnameDef = {
    "get_server_hostname",                  // name
    (PyCFunction)PyGetServerHostname,       // method
    METH_VARARGS | METH_KEYWORDS,           // flags
    "get_server_hostname() -> str\n"
    "\n"
    "(internal)",
};

auto PythonMethodsServerHostname::GetMethods() -> std::vector<PyMethodDef> {
  return {
      PySetServerHostnameDef,
      PyGetServerHostnameDef,
  };
}

}  // namespace ballistica::scene_v1