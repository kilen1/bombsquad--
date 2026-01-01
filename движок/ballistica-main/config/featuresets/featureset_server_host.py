# Released under the MIT License. See LICENSE for details.
#
# pylint: disable=missing-module-docstring

from __future__ import annotations

from typing import TYPE_CHECKING

import ba
from efro.error import expect_error

if TYPE_CHECKING:
    from typing import Any, Literal


class ServerHostFeatureSet(ba.FeatureSet):
    """Feature set for server host functionality."""

    @classmethod
    def get_name(cls) -> str:
        """GetName for this feature-set."""
        return 'server_host'

    @classmethod
    def get_implementation_name(cls) -> str:
        """Get the name of our associated C++ implementation."""
        return 'server_host'

    @classmethod
    def get_min_server_version(cls) -> tuple[int, int]:
        """Minimum server version supporting this feature-set."""
        return (1, 0)

    @classmethod
    def get_min_app_version(cls) -> tuple[int, int]:
        """Minimum app version supporting this feature-set."""
        return (1, 0)

    @classmethod
    def get_description(cls) -> str:
        """Get a description for this feature-set."""
        return 'Server host name management functionality'

    @classmethod
    def get_author(cls) -> str:
        """Get the author for this feature-set."""
        return 'BombSquad User'

    @classmethod
    def get_supports_testing(cls) -> bool:
        """Whether this feature-set supports testing."""
        return True

    @classmethod
    def get_min_python_version(cls) -> tuple[int, ...]:
        """Minimum Python version required for this feature-set."""
        return (3, 7)

    def on_module_exec(self, module: Any) -> None:
        """Called when our associated Python module is executed."""
        # We could potentially add classes or import other modules here
        pass

    def get_py_to_native_call_targets(self) -> dict[str, str]:
        """Return mapping of Python-call-target names to C++ method names."""
        return {}

    def get_feature_types(self) -> list[Literal['server_host']]:
        """Return the types of features this feature-set provides."""
        return ['server_host']