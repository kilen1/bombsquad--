# Released under the MIT License. See LICENSE for details.
#
"""Module for changing the local hostname/account name in BombSquad."""

from __future__ import annotations

import _babase
import babase
import bauiv1 as bui
import baplus


def change_local_hostname(new_hostname: str) -> None:
    """Change the local account name (hostname) in BombSquad.
    
    Args:
        new_hostname: The new hostname/account name to set
    """
    # Validate the input
    if not isinstance(new_hostname, str) or not new_hostname.strip():
        raise ValueError("Hostname must be a non-empty string")
    
    # Sanitize the hostname to ensure it's valid UTF-8 and not too long
    hostname = babase.utils.get_clean_utf8_text(new_hostname.strip(), "hostname")
    if len(hostname) > 50:  # Limit length to prevent issues
        hostname = hostname[:50]
    
    # Get the current account state
    plus = baplus.app.plus
    if plus is None:
        raise RuntimeError("Plus subsystem not available")
    
    # Check if we're currently signed in with a local account
    current_state = plus.get_v1_account_state()
    current_type = plus.get_v1_account_type()
    
    # The actual account name is managed by the native layer and depends on account type
    # For local accounts, we need to update the account sign-in state to change the display name
    # This is the proper way to change the local account name in the UI
    _babase.set_account_sign_in_state(current_state == 'signed_in', hostname)
    
    # Update the configuration to preserve the setting
    config = babase.app.config
    config['LocalHostName'] = hostname
    config.commit()
    
    # If we were signed in, we might need to refresh the account state to ensure consistency
    if current_state == 'signed_in':
        # Refresh the account profile to update any related UI elements
        plus.add_v1_account_transaction({
            'type': 'SET_ACCOUNT_NAME',
            'name': hostname
        })
        plus.run_v1_account_transactions()


def get_current_hostname() -> str:
    """Get the current local hostname/account name.
    
    Returns:
        The current hostname/account name
    """
    plus = baplus.app.plus
    if plus is None:
        return "Unknown"
    
    # If signed in, get the account name
    if plus.get_v1_account_state() == "signed_in":
        return plus.get_v1_account_name()
    else:
        # If not signed in, return the configured hostname or device name
        config = babase.app.config
        hostname = config.get('LocalHostName', None)
        if hostname:
            return hostname
        else:
            # Fallback to device name
            return _babase.get_device_name()


class HostnameChangerWindow(bui.Window):
    """Window for changing the local hostname."""
    
    def __init__(self, transition: str = 'in_right'):
        app = bui.app
        uiscale = app.ui_v1.uiscale
        
        self._width = 500 if uiscale is bui.UIScale.SMALL else 400
        self._height = 250 if uiscale is bui.UIScale.SMALL else 200
        
        super().__init__(
            root_widget=bui.containerwidget(
                size=(self._width, self._height),
                background=False,
                transition=transition,
                scale=2.0 if uiscale is bui.UIScale.SMALL else 1.3 if uiscale is bui.UIScale.MEDIUM else 1.0
            )
        )
        
        bui.textwidget(
            parent=self._root_widget,
            position=(self._width * 0.5, self._height - 40),
            size=(0, 0),
            text='Change Local Hostname',
            h_align='center',
            v_align='center',
            color=app.ui_v1.title_color,
            scale=1.0,
            maxwidth=self._width * 0.9
        )
        
        current_hostname = get_current_hostname()
        
        bui.textwidget(
            parent=self._root_widget,
            position=(self._width * 0.5, self._height - 80),
            size=(0, 0),
            text=f'Current: {current_hostname}',
            h_align='center',
            v_align='center',
            color=(0.7, 0.7, 0.7),
            scale=0.7,
            maxwidth=self._width * 0.8
        )
        
        self._text_field = bui.textwidget(
            parent=self._root_widget,
            position=(self._width * 0.5 - 150, self._height - 130),
            size=(300, 40),
            text=current_hostname,
            h_align='left',
            v_align='center',
            max_chars=50,
            autoselect=True,
            editable=True,
            description='New hostname'
        )
        
        bui.buttonwidget(
            parent=self._root_widget,
            position=(self._width * 0.5 - 100, self._height - 180),
            size=(200, 40),
            label='Change Hostname',
            on_activate_call=self._change_hostname
        )
        
        bui.containerwidget(
            edit=self._root_widget,
            on_cancel_call=self.main_window_back,
            selected_child=self._text_field
        )
    
    def _change_hostname(self) -> None:
        """Handle the change hostname button press."""
        new_hostname = bui.textwidget(query=self._text_field)
        if new_hostname and new_hostname.strip():
            try:
                change_local_hostname(new_hostname)
                bui.screenmessage('Hostname changed successfully!', color=(0, 1, 0))
                bui.getsound('gunCocking').play()
            except Exception as e:
                bui.screenmessage(f'Error changing hostname: {e}', color=(1, 0, 0))
                bui.getsound('error').play()
        else:
            bui.screenmessage('Please enter a valid hostname', color=(1, 0, 0))
            bui.getsound('error').play()
        
        bui.containerwidget(edit=self._root_widget, selected_child=None)
        bui.timer(1.0, self.main_window_back)


def show_hostname_changer_window() -> None:
    """Show the hostname changer window."""
    HostnameChangerWindow()