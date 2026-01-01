/* This file is part of the Ballistica project. */
/* See the COPYING file at the top-level directory of this distribution. */

#ifndef BALLISTICA_SERVER_HOST_PYTHON_METHODS_PYTHON_METHODS_SERVER_HOST_H_
#define BALLISTICA_SERVER_HOST_PYTHON_METHODS_PYTHON_METHODS_SERVER_HOST_H_

#include <vector>

#include "ballistica/server_host/server_host.h"

namespace ballistica::server_host {

class PythonMethodsServerHost {
 public:
  static auto GetMethods() -> std::vector<PyMethodDef>;
};

}  // namespace ballistica::server_host

#endif  // BALLISTICA_SERVER_HOST_PYTHON_METHODS_PYTHON_METHODS_SERVER_HOST_H_