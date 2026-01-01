# Server Host Feature Set

This feature set provides functionality for managing the server host name in BombSquad.

## Overview

The Server Host feature set allows changing and retrieving the local name of the server host. This is useful for identifying servers in multiplayer games.

## Functionality

- Get the current server host name
- Set a custom server host name
- Get the default server host name

## Python API

The functionality is exposed through the `_baserverhost` Python module with the following functions:

- `get_host_name()` - Returns the current server host name
- `set_host_name(name: str)` - Sets the server host name to the specified value
- `get_default_host_name()` - Returns the default server host name

## Example Usage

```python
import _baserverhost

# Get current server name
current_name = _baserverhost.get_host_name()
print(f"Current server name: {current_name}")

# Set a new server name
_baserverhost.set_host_name("My Custom Server")

# Get the default server name
default_name = _baserverhost.get_default_host_name()
print(f"Default server name: {default_name}")
```