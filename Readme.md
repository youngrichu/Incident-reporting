# Hidden Leaf Incident Reporting

## Contributors
YolkWorks

## Tags
incident reporting, security, plugin

## Requirements
- Requires at least: 5.0
- Tested up to: 6.2
- Stable tag: 1.0

## Description
A plugin for reporting and managing security incidents.

## Features
- Easy installation and activation
- Shortcode support for displaying the incident report form
- Data management for security incidents

## Installation
1. Upload the plugin files to the `/wp-content/plugins/incident-reporting` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Use the shortcode `[incident_report_form]` to display the form.

## Usage
After installation, you can use the shortcode in any post or page to display the incident reporting form.

## Changelog
### 1.3
Fix form display issues and enhance functionality

- Resolved the issue of double required symbols in the incident report form.
- Ensured all captured fields, including "Actions Already Taken," are displayed in the incident details.
- Modified the upload alert to only show when files are attached, preventing unnecessary alerts.
- Updated the success alert to redirect users to the home page upon clicking "Done."
- Added a download button for attachments in the incident details view.
- Ensured the status update functionality is present and operational.

These changes improve user experience and maintain the integrity of the incident reporting process.

## Uninstalling
To uninstall the plugin, deactivate it from the 'Plugins' menu. If you want to remove all data associated with the plugin, you can delete the plugin, which will also drop the incidents table from the database.

## Contributing
Contributions are welcome! If you have suggestions or improvements, feel free to open an issue or submit a pull request.

## License
This plugin is licensed under the GPL2 license. See the LICENSE file for more details.

## Author
**YolkWorks**  
[Website](https://yolk.works)  
[Plugin URI](https://hiddenleaf.org)
