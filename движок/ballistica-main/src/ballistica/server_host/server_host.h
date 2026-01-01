/* This file is part of the Ballistica project. */
/* See the COPYING file at the top-level directory of this distribution. */

#ifndef BALLISTICA_SERVER_HOST_SERVER_HOST_H_
#define BALLISTICA_SERVER_HOST_SERVER_HOST_H_

#include <string>

#include "ballistica/base/base.h"

namespace ballistica::server_host {

class ServerHost {
 public:
  ServerHost();
  ~ServerHost() = default;

  // Get the current server host name
  static std::string GetHostName();
  
  // Set the server host name
  static void SetHostName(const std::string& name);
  
  // Get the default server host name
  static std::string GetDefaultHostName();

 private:
  static std::string host_name_;
};

}  // namespace ballistica::server_host

#endif  // BALLISTICA_SERVER_HOST_SERVER_HOST_H_