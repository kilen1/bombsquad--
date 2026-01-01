/* This file is part of the Ballistica project. */
/* See the COPYING file at the top-level directory of this distribution. */

#ifndef BALLISTICA_SERVER_HOST_PYTHON_SERVER_HOST_PYTHON_H_
#define BALLISTICA_SERVER_HOST_PYTHON_SERVER_HOST_PYTHON_H_

#include "ballistica/shared/python/python_object_set.h"
#include "ballistica/server_host/server_host.h"

namespace ballistica::server_host {

/// General Python support class for our server host feature-set.
class ServerHostPython {
 public:
  void AddPythonClasses(PyObject* module);
  void ImportPythonObjs();
  const auto& objs() { return objs_; }

 private:
  PythonObjectSet<void> objs_;
};

}  // namespace ballistica::server_host

#endif  // BALLISTICA_SERVER_HOST_PYTHON_SERVER_HOST_PYTHON_H_