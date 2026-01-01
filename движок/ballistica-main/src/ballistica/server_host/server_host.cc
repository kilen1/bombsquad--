/* This file is part of the Ballistica project. */
/* See the COPYING file at the top-level directory of this distribution. */

#include "ballistica/server_host/server_host.h"

#include <string>

#include "ballistica/core/platform/platform.h"
#include "ballistica/shared/foundation/exceptions.h"
#include "ballistica/shared/python/python_module_builder.h"
#include "ballistica/server_host/python/methods/python_methods_server_host.h"

namespace ballistica::server_host {

// Static member definition
std::string ServerHost::host_name_;

ServerHost::ServerHost() {
  // Initialize with default host name if not already set
  if (host_name_.empty()) {
    host_name_ = GetDefaultHostName();
  }
}

std::string ServerHost::GetHostName() {
  if (host_name_.empty()) {
    host_name_ = GetDefaultHostName();
  }
  return host_name_;
}

void ServerHost::SetHostName(const std::string& name) {
  // Validate the host name
  if (name.empty()) {
    throw Exception("Server host name cannot be empty");
  }
  
  // In a real implementation, you might want to validate the name format
  // For now, just set it
  host_name_ = name;
}

std::string ServerHost::GetDefaultHostName() {
  // Get the device name from the platform
  auto* platform = core::Platform::Get();
  if (platform) {
    std::string device_name = platform->GetDeviceName();
    if (!device_name.empty()) {
      return "BombSquad Server (" + device_name + ")";
    }
  }
  
  // Fallback to a default name
  return "BombSquad Server";
}

// Declare a plain C PyInit_XXX function for our Python module. This is how
// Python inits our binary module (and by extension, our entire
// feature-set).
extern "C" auto PyInit__baserverhost() -> PyObject* {
  auto* builder = new PythonModuleBuilder(
      "_baserverhost",

      // Our native methods.
      {PythonMethodsServerHost::GetMethods()},

      // Our module exec. Here we can add classes, import other modules, or
      // whatever else (same as a regular Python script module).
      [](PyObject* module) -> int {
        BA_PYTHON_TRY;
        // Any module execution code would go here
        return 0;
        BA_PYTHON_INT_CATCH;
      });
  return builder->Build();
}

}  // namespace ballistica::server_host