// Released under the MIT License. See LICENSE for details.
//
// Additional methods related to server functionality.

#include "ballistica/scene_v1/python/methods/python_methods_server_extensions.h"

#include "ballistica/base/python/support/python_context_call.h"
#include "ballistica/classic/support/classic_app_mode.h"
#include "ballistica/core/python/core_python.h"
#include "ballistica/scene_v1/python/scene_v1_python.h"

namespace ballistica::scene_v1 {

// Method to get server hostname
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

// Method to set server hostname
static auto PySetServerHostname(PyObject* self, PyObject* args, PyObject* keywds)
    -> PyObject* {
  BA_PYTHON_TRY;
  const char* hostname;
  static const char* kwlist[] = {"hostname", nullptr};
  if (!PyArg_ParseTupleAndKeywords(args, keywds, "s",
                                   const_cast<char**>(kwlist), &hostname)) {
    return nullptr;
  }

  auto* appmode = classic::ClassicAppMode::GetActiveOrThrow();
  appmode->set_server_hostname(std::string(hostname));

  Py_RETURN_NONE;
  BA_PYTHON_CATCH;
}

// Method to handle /me command
static auto PyHandleMeCommand(PyObject* self, PyObject* args, PyObject* keywds)
    -> PyObject* {
  BA_PYTHON_TRY;
  const char* action;
  int client_id;
  static const char* kwlist[] = {"action", "client_id", nullptr};
  if (!PyArg_ParseTupleAndKeywords(args, keywds, "si",
                                   const_cast<char**>(kwlist), &action, &client_id)) {
    return nullptr;
  }

  // Get the player's name based on client_id from the game roster
  auto* appmode = classic::ClassicAppMode::GetActiveOrThrow();
  
  // Get the game roster from the app mode
  cJSON* roster = appmode->game_roster();
  std::string player_name = "Unknown Player";
  
  if (roster && cJSON_IsArray(roster)) {
    int array_size = cJSON_GetArraySize(roster);
    for (int i = 0; i < array_size; i++) {
      cJSON* player = cJSON_GetArrayItem(roster, i);
      if (player) {
        cJSON* client_id_json = cJSON_GetObjectItem(player, "i");
        if (client_id_json && cJSON_IsNumber(client_id_json)) {
          int player_client_id = client_id_json->valueint;
          if (player_client_id == client_id) {
            cJSON* spec_json = cJSON_GetObjectItem(player, "spec");
            if (spec_json && cJSON_IsString(spec_json)) {
              // Parse the player spec JSON to get the name
              cJSON* spec = cJSON_Parse(spec_json->valuestring);
              if (spec) {
                cJSON* name_json = cJSON_GetObjectItem(spec, "n");
                if (name_json && cJSON_IsString(name_json)) {
                  player_name = std::string(name_json->valuestring);
                }
                cJSON_Delete(spec);
              }
            }
            break;
          }
        }
      }
    }
  }
  
  // Format the /me message: "* player_name performs action"
  std::string formatted_msg = std::string("* ") + player_name + std::string(" ") + std::string(action);
  
  return PyUnicode_FromString(formatted_msg.c_str());
  BA_PYTHON_CATCH;
}

static PyMethodDef PyGetServerHostnameDef = {
    "get_server_hostname",                  // name
    (PyCFunction)PyGetServerHostname,       // method
    METH_VARARGS | METH_KEYWORDS,           // flags
    "get_server_hostname() -> str\n"
    "\n"
    "(internal)",
};

static PyMethodDef PySetServerHostnameDef = {
    "set_server_hostname",                  // name
    (PyCFunction)PySetServerHostname,       // method
    METH_VARARGS | METH_KEYWORDS,           // flags
    "set_server_hostname(hostname: str) -> None\n"
    "\n"
    "(internal)",
};

static PyMethodDef PyHandleMeCommandDef = {
    "handle_me_command",                    // name
    (PyCFunction)PyHandleMeCommand,         // method
    METH_VARARGS | METH_KEYWORDS,           // flags
    "handle_me_command(action: str, client_id: int) -> str\n"
    "\n"
    "(internal)",
};

auto PythonMethodsServerExtensions::GetMethods() -> std::vector<PyMethodDef> {
  return {
      PyGetServerHostnameDef,
      PySetServerHostnameDef,
      PyHandleMeCommandDef,
  };
}

}  // namespace ballistica::scene_v1