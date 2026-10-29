# 🛠️ Installation

1. Download the `.zip` file or clone the repository.
2. Extract the folder into your Moodle `local/` directory and name it `studiolms`.
   Final path: `your-moodle/local/studiolms/`
3. Visit **Site administration > Notifications** to finish the installation.
4. Roles that should use the plugin need two capabilities, granted by default to editing teachers and managers:
   * `local/studiolms:generate` for the generation wizard;
   * `local/studiolms:restyle` for restyling an existing page or label.
5. The **StudioLMS** item appears in the course navigation, and **Restyle with StudioLMS** in the settings of a page, for users who hold those capabilities.

Creating content also needs the Moodle capabilities for it (`moodle/course:manageactivities`, and `mod/<type>:addinstance` for each type), and restyling needs the backup and restore capabilities of the activity. StudioLMS never grants more than Moodle itself would.

The settings page (**Site administration > Plugins > Local plugins > StudioLMS**) only explains where the AI comes from. There is no key to enter.
