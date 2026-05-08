# Windows Installer for POS System

This installer package deploys the POS System web application into a Windows XAMPP installation.

## What it does

- Copies all repository files into the selected install directory
- Defaults to `C:\xampp\htdocs\pos`
- Creates a desktop shortcut that opens `http://localhost/pos`
- Opens the browser after installation

## Prerequisites

1. Install XAMPP with Apache and MySQL/MariaDB.
2. Install Inno Setup 6 to compile the installer.
3. Make sure the web server is configured to serve PHP from the selected destination.

## Build the installer

1. Open `installer\build_installer.cmd` in the repository.
2. Run it from the `installer` folder.
3. The compiled installer will be created as `POS_System_Installer.exe` in the same folder.

## Using the installer

1. Run `POS_System_Installer.exe`.
2. Choose `C:\xampp\htdocs\pos` or your XAMPP `htdocs` folder.
3. Complete installation.
4. Start Apache and MySQL in XAMPP.
5. Visit `http://localhost/pos/setup.php` to create the database.
6. Open `http://localhost/pos` to begin using the application.

## Notes

- If your MySQL root password is not blank, update `config\config.php` after installation.
- This installer deploys the PHP app only; XAMPP must already be installed and running.
