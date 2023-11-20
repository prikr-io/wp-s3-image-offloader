// main.js
const fs = require('fs').promises;
const path = require('path');
const { promptForNewVersion, closeInterface } = require('./modules/userPrompts');

const infoJsonFile = path.join(__dirname, '..', 'info.json');
const mainPluginFile = path.join(__dirname, '..', 'prikr-image-offloader.php');
const packageJsonFile = path.join(__dirname, '..', 'package.json');


// Function to update the JSON file
async function updateInfoJsonFile(newVersion) {
    try {
        const data = await fs.readFile(infoJsonFile, 'utf8');
        const json = JSON.parse(data);
        json.version = newVersion;
        await fs.writeFile(infoJsonFile, JSON.stringify(json, null, 2), 'utf8');
        console.log(`Version updated to ${newVersion} in JSON file`);
    } catch (err) {
        console.error('Error updating JSON file:', err);
    }
}

// Function to update the PHP file
async function updateMainPluginFile(newVersion) {
    try {
        const data = await fs.readFile(mainPluginFile, 'utf8');
        const currentVersionMatch = /Version: (\d+\.\d+\.\d+)/.exec(data);
        if (!currentVersionMatch) {
            console.error('Error finding current version in PHP file.');
            return;
        }

        const updatedPhpContent = data.replace(currentVersionMatch[0], `Version: ${newVersion}`);
        await fs.writeFile(mainPluginFile, updatedPhpContent, 'utf8');
        console.log(`Version updated to ${newVersion} in PHP file`);
    } catch (err) {
        console.error('Error updating PHP file:', err);
    }
}

// Function to update the package.json file
async function updatePackageJsonFile(newVersion) {
    try {
        const data = await fs.readFile(packageJsonFile, 'utf8');
        const packageJson = JSON.parse(data);
        packageJson.version = newVersion;
        await fs.writeFile(packageJsonFile, JSON.stringify(packageJson, null, 2), 'utf8');
        console.log(`Version updated to ${newVersion} in package.json`);
    } catch (err) {
        console.error('Error updating package.json file:', err);
    }
}

// Call the update functions
promptForNewVersion(packageJsonFile)
    .then((newVersion) => Promise.all([
        updateInfoJsonFile(newVersion),
        updateMainPluginFile(newVersion),
        updatePackageJsonFile(newVersion),
    ]))
    .then(closeInterface)
    .catch((err) => {
        console.error('Error updating files:', err);
        closeInterface();
    });
