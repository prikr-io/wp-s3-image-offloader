/**
 * For this plugin to update, we'd need to update the version in 2 seperate places. 
 * In both the info.json file and the main plugin file (prikr-image-offloader.php).
 * For semantics, we'll also update the package.json file.
 */
const fs = require('fs');
const path = require('path');

const infoJsonFile = path.join(__dirname, '..', 'info.json');
const mainPluginFile = path.join(__dirname, '..', 'prikr-image-offloader.php');
const packageJsonFile = path.join(__dirname, '..', 'package.json');

// Read and increment the current version by 0.1
function incrementVersion(version) {
    const [major, minor] = version.split('.').map(Number);
    return `${major}.${(minor + 0.1).toFixed(1)}`;
}

// Update the JSON file
function updateInfoJsonFile() {
    fs.readFile(infoJsonFile, 'utf8', (err, data) => {
        if (err) {
        console.error('Error reading the JSON file:', err);
        return;
        }

        try {
        const json = JSON.parse(data);
        const currentVersion = json.version;
        const newVersion = incrementVersion(currentVersion);
        json.version = newVersion;

        fs.writeFile(infoJsonFile, JSON.stringify(json, null, 2), 'utf8', (err) => {
            if (err) {
            console.error('Error writing the updated JSON file:', err);
            } else {
            console.log(`Version updated to ${newVersion} in JSON file`);
            }
        });
        } catch (parseError) {
        console.error('Error parsing JSON:', parseError);
        }
    });
}

// Update the PHP file
function updateMainPluginFile() {
    fs.readFile(mainPluginFile, 'utf8', (err, data) => {
        if (err) {
        console.error('Error reading the PHP file:', err);
        return;
        }

        // Use regular expression to find and replace the version attribute
        const currentVersionMatch = /Version: (\d+\.\d+)/g.exec(data);
        if (!currentVersionMatch) {
        console.error('Error finding current version in PHP file.');
        return;
        }

        const currentVersion = currentVersionMatch[1];
        const newVersion = incrementVersion(currentVersion);
        const updatedPhpContent = data.replace(currentVersionMatch[0], `Version: ${newVersion}`);

        fs.writeFile(mainPluginFile, updatedPhpContent, 'utf8', (err) => {
        if (err) {
            console.error('Error writing the updated PHP file:', err);
        } else {
            console.log(`Version updated to ${newVersion} in PHP file`);
        }
        });
    });
}

// Update the package.json file
function updatePackageJsonFile() {
    fs.readFile(packageJsonFile, 'utf8', (err, data) => {
        if (err) {
        console.error('Error reading the package.json file:', err);
        return;
        }

        try {
        const packageJson = JSON.parse(data);
        const currentVersion = packageJson.version;
        const newVersion = incrementVersion(currentVersion);
        packageJson.version = newVersion;

        fs.writeFile(packageJsonFile, JSON.stringify(packageJson, null, 2), 'utf8', (err) => {
            if (err) {
            console.error('Error writing the updated package.json file:', err);
            } else {
            console.log(`Version updated to ${newVersion} in package.json`);
            }
        });
        } catch (parseError) {
        console.error('Error parsing package.json:', parseError);
        }
    });
}

updateInfoJsonFile();
updateMainPluginFile();
updatePackageJsonFile();