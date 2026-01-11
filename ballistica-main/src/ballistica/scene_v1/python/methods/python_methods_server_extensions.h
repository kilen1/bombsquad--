// Released under the MIT License. See LICENSE for details.
//
// Additional methods related to server functionality.

#ifndef BALLISTICA_SCENE_V1_PYTHON_METHODS_PYTHON_METHODS_SERVER_EXTENSIONS_H_
#define BALLISTICA_SCENE_V1_PYTHON_METHODS_PYTHON_METHODS_SERVER_EXTENSIONS_H_

#include <vector>

#include "ballistica/base/python/base_python.h"

namespace ballistica::scene_v1 {

class PythonMethodsServerExtensions {
 public:
  static auto GetMethods() -> std::vector<PyMethodDef>;
};

}  // namespace ballistica::scene_v1

#endif  // BALLISTICA_SCENE_V1_PYTHON_METHODS_PYTHON_METHODS_SERVER_EXTENSIONS_H_