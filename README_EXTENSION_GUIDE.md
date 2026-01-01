# How to Extend Ballistica Engine with C++

## Overview
The Ballistica engine (used by BombSquad) allows C++ extensions through a feature-set system. Each feature-set is a self-contained module that can provide both C++ and Python functionality.

## Key Concepts

### Feature Sets
- Feature sets are high-level subsets of the engine that can be easily added/removed
- Each feature set has its own directory under `/src/ballistica/`
- Feature sets follow naming convention: `lowercase_with_underscores` (e.g., `my_feature`)
- Each feature set gets compiled into a Python module with prefix `_ba` (e.g., `_bamyfeature`)

### Directory Structure
```
/src/ballistica/my_feature/
├── my_feature.h          # Main feature set header
├── my_feature.cc         # Main feature set implementation
├── python/
│   ├── my_feature_python.h
│   ├── my_feature_python.cc
│   ├── class/
│   │   ├── python_class_*.h
│   │   └── python_class_*.cc
│   └── methods/
│       ├── python_methods_my_feature.h
│       └── python_methods_my_feature.cc
```

## How to Create a New Feature Set

### 1. Create Feature Set Files

**Header file (`my_feature.h`):**
```cpp
#ifndef BALLISTICA_MY_FEATURE_MY_FEATURE_H_
#define BALLISTICA_MY_FEATURE_MY_FEATURE_H_

#include "ballistica/shared/foundation/feature_set_native_component.h"

// Predeclare types from other feature sets that we use
namespace ballistica::core {
class CoreFeatureSet;
}
namespace ballistica::base {
class BaseFeatureSet;
}

namespace ballistica::my_feature {

// Predeclare types we use throughout our FeatureSet
class MyFeatureFeatureSet;
class MyFeaturePython;

// Our feature-set's globals
extern core::CoreFeatureSet* g_core;
extern base::BaseFeatureSet* g_base;
extern MyFeatureFeatureSet* g_my_feature;

class MyFeatureFeatureSet : public FeatureSetNativeComponent {
 public:
  static auto Import() -> MyFeatureFeatureSet*;
  static void OnModuleExec(PyObject* module);
  
  // Your C++ methods here
  void MyMethod() const;

  MyFeaturePython* const python;

 private:
  MyFeatureFeatureSet();
};

}  // namespace ballistica::my_feature

#endif  // BALLISTICA_MY_FEATURE_MY_FEATURE_H_
```

**Implementation file (`my_feature.cc`):**
```cpp
#include "ballistica/my_feature/my_feature.h"

#include "ballistica/base/base.h"
#include "ballistica/core/core.h"
#include "ballistica/my_feature/python/my_feature_python.h"

namespace ballistica::my_feature {

MyFeatureFeatureSet* g_my_feature{};
base::BaseFeatureSet* g_base{};
core::CoreFeatureSet* g_core{};

void MyFeatureFeatureSet::OnModuleExec(PyObject* module) {
  // Import core first
  g_core = core::CoreFeatureSet::Import();
  
  // Create our feature-set's C++ front-end
  g_my_feature = new MyFeatureFeatureSet();
  
  // Store our C++ front-end with our Python module
  g_my_feature->StoreOnPythonModule(module);
  
  // Import Python objects
  g_my_feature->python->ImportPythonObjs();
  
  // Import other C++ feature-set-front-ends
  g_base = base::BaseFeatureSet::Import();
  
  // Define our module's classes
  g_my_feature->python->AddPythonClasses(module);
}

MyFeatureFeatureSet::MyFeatureFeatureSet() : python{new MyFeaturePython()} {
  assert(g_my_feature == nullptr);  // Singleton check
}

auto MyFeatureFeatureSet::Import() -> MyFeatureFeatureSet* {
  return ImportThroughPythonModule<MyFeatureFeatureSet>("_bamyfeature");
}

void MyFeatureFeatureSet::MyMethod() const { 
  python->MyMethod(); 
}

}  // namespace ballistica::my_feature
```

### 2. Create Python Integration

**Python header (`python/my_feature_python.h`):**
```cpp
#ifndef BALLISTICA_MY_FEATURE_PYTHON_MY_FEATURE_PYTHON_H_
#define BALLISTICA_MY_FEATURE_PYTHON_MY_FEATURE_PYTHON_H_

#include "ballistica/shared/python/python_object_set.h"
#include "ballistica/my_feature/my_feature.h"

namespace ballistica::my_feature {

class MyFeaturePython {
 public:
  void MyMethod();
  
  enum class ObjID {
    kMyCallable,  // if you need to call Python from C++
    kLast
  };
  
  void AddPythonClasses(PyObject* module);
  void ImportPythonObjs();
  const auto& objs() { return objs_; }

 private:
  PythonObjectSet<ObjID> objs_;
};

}  // namespace ballistica::my_feature

#endif
```

**Python implementation (`python/my_feature_python.cc`):**
```cpp
#include "ballistica/my_feature/python/my_feature_python.h"

#include "ballistica/shared/python/python_module_builder.h"
#include "ballistica/my_feature/python/methods/python_methods_my_feature.h"

namespace ballistica::my_feature {

extern "C" auto PyInit__bamyfeature() -> PyObject* {
  auto* builder = new PythonModuleBuilder(
      "_bamyfeature",
      {PythonMethodsMyFeature::GetMethods()},  // Native methods
      [](PyObject* module) -> int {
        BA_PYTHON_TRY;
        MyFeatureFeatureSet::OnModuleExec(module);
        return 0;
        BA_PYTHON_INT_CATCH;
      });
  return builder->Build();
}

void MyFeaturePython::AddPythonClasses(PyObject* module) {
  // Add any custom Python classes here
  // PythonModuleBuilder::AddClass<PythonClassMyClass>(module);
}

void MyFeaturePython::ImportPythonObjs() {
  // Import Python objects from Python layer
  // This file is auto-generated by build system
  #include "ballistica/my_feature/mgen/pyembed/binding_my_feature.inc"
}

void MyFeaturePython::MyMethod() {
  auto gil{Python::ScopedInterpreterLock()};
  // Your implementation here
}

}  // namespace ballistica::my_feature
```

### 3. Add Methods and Classes

**Native methods (`python/methods/python_methods_my_feature.cc`):**
```cpp
#include "ballistica/my_feature/python/methods/python_methods_my_feature.h"

#include "ballistica/core/core.h"
#include "ballistica/shared/python/python_macros.h"

namespace ballistica::my_feature {

static auto PyMyFunction(PyObject* self, PyObject* args, PyObject* keywds)
    -> PyObject* {
  BA_PYTHON_TRY;
  // Parse arguments
  const char* param;
  static const char* kwlist[] = {"param", nullptr};
  if (!PyArg_ParseTupleAndKeywords(args, keywds, "s", 
                                   const_cast<char**>(kwlist), &param)) {
    return nullptr;
  }
  
  g_core->logging->Log(LogName::kBa, LogLevel::kInfo, 
                       "MyFunction called with: " + std::string(param));
  Py_RETURN_NONE;
  BA_PYTHON_CATCH;
}

static PyMethodDef PyMyFunctionDef = {
    "my_function", 
    (PyCFunction)PyMyFunction, 
    METH_VARARGS | METH_KEYWORDS,
    "my_function(param: str) -> None\n\nMy custom function."
};

auto PythonMethodsMyFeature::GetMethods() -> std::vector<PyMethodDef> {
  return {PyMyFunctionDef};
}

}  // namespace ballistica::my_feature
```

**Custom Python class (`python/class/python_class_my_class.cc` and `.h`):**
See the template feature set for examples of how to create custom Python classes that wrap C++ functionality.

### 4. Register Your Feature Set

Create a feature set definition file in `/config/featuresets/featureset_my_feature.py`:
```python
# Feature set definition
from efrotools import flag_enabled

name = 'my_feature'
display_name = 'My Feature'
enabled = True
dependencies = ['base', 'core']  # Other feature sets this depends on
```

### 5. Build System Integration

The build system will automatically detect your feature set if you follow the naming conventions and directory structure. The meta build system will generate necessary binding files.

## Key Patterns

1. **Feature Set Import Pattern**: Other code can import your feature set with `MyFeatureFeatureSet::Import()`
2. **Thread Safety**: Always use `BA_PYTHON_TRY`/`BA_PYTHON_CATCH` for Python interactions
3. **GIL Management**: Use `Python::ScopedInterpreterLock()` when calling Python from different threads
4. **Singleton Pattern**: Feature sets are singletons with static `Import()` method
5. **Namespace Isolation**: Each feature set lives in its own namespace to avoid conflicts

## Examples in the Codebase

- **Template Feature Set**: `/src/ballistica/template_fs/` - Complete example of a minimal feature set
- **Scene V1**: `/src/ballistica/scene_v1/` - More complex example with custom node classes
- **Base**: `/src/ballistica/base/` - Core functionality example

## Building Your Extension

After creating your feature set, run:
```bash
make env
make meta
make assets-cmake  # or appropriate asset target
make prefab-gui-debug-build  # to build the engine
```

Your new feature set will be available as a Python module that can be imported and used in the game.