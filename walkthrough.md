# Admin Interface Update Walkthrough

## Changes Implemented

### 1. Authentication System

- **Automatic Login**: The interface now checks `localStorage` for `auth_token` on load. If found, it skips the login screen.
- **Login Overlay**: A modal overlay appears if no token is found or if the session is invalid.
- **Logout Functionality**: Added a "Se déconnecter" button in the sidebar that clears the token and shows the login overlay.
- **Session Handling**: 401 Unauthorized responses from the API automatically trigger a logout.

### 2. UI Enchancements

- **Logo**: Added `LogoDabApp.png` to the sidebar.
- **Sticky Auth State**: The API Base URL is saved to `localStorage` so it persists across refreshes.

## Verification Steps

1. **Initial Load**:
    - Open `http://localhost:8000/admin/guide-admin.html` in your browser.
    - You should see the login overlay.

2. **Login**:
    - Enter your credentials (email/phone and password).
    - Click "Se connecter".
    - The overlay should disappear and the dashboard should load.

3. **Reload**:
    - Refresh the page.
    - You should remain logged in without seeing the overlay.

4. **Logout**:
    - Click the "Se déconnecter" button in the sidebar bottom.
    - You should be returned to the login overlay.

5. **Token Expiry (Simulation)**:
    - While logged in, manually delete `auth_token` from Application > Local Storage in DevTools.
    - Perform any action (e.g., refresh list).
    - You should be logged out automatically.
