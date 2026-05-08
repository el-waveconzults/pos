[Setup]
AppId={{c94c5ac3-f753-418b-8cec-6d311d2b9f85}}
AppName=POS System
AppVersion=1.0.0
AppPublisher=POS System
AppPublisherURL=https://example.com
AppSupportURL=https://example.com
AppUpdatesURL=https://example.com
DefaultDirName=C:\xampp\htdocs\pos
DisableProgramGroupPage=yes
OutputDir=.
OutputBaseFilename=POS_System_Installer
Compression=lzma
SolidCompression=yes
WizardStyle=modern
ArchitecturesInstallIn64BitMode=x64
InfoBeforeFile=README_INSTALLER.md

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: desktopicon; Description: "Create a desktop shortcut to launch POS System"; GroupDescription: "Additional icons:"; Flags: unchecked

[Files]
Source: "..\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion; Excludes: ".git\*;installer\*"

[Icons]
Name: "{group}\POS System"; Filename: "{cmd}"; Parameters: "/C start http://localhost/pos"; WorkingDir: "{app}"
Name: "{userdesktop}\POS System"; Filename: "{cmd}"; Parameters: "/C start http://localhost/pos"; WorkingDir: "{app}"; Tasks: desktopicon

[Run]
Filename: "{cmd}"; Parameters: "/C start http://localhost/pos"; Description: "Launch POS System in your browser"; Flags: nowait postinstall skipifsilent
