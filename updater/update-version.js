const fs = require('fs').promises;
const path = require('path');
const readline = require('readline');

const infoJsonFile = path.join(__dirname, '..', 'info.json');
const mainPluginFile = path.join(__dirname, '..', 'prikr-image-offloader.php');
const packageJsonFile = path.join(__dirname, '..', 'package.json');

const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

let newVersion; // Variable to store the user-inputted version

// Function to get the current version from a file
async function getCurrentVersionFromFile(filePath) {
    try {
        const fileContent = await fs.readFile(filePath, 'utf8');
        const versionMatch = /"version": "(\d+\.\d+\.\d+)"/.exec(fileContent);
        return versionMatch ? versionMatch[1] : null;
    } catch (err) {
        console.error(`Error reading version from ${filePath}:`, err);
        return null;
    }
}

// Function to prompt the user for the new version
async function promptForNewVersion() {
    const currentVersion = await getCurrentVersionFromFile(packageJsonFile) || '0.0.1';

    async function ask() {
        return new Promise((resolve) => {
            rl.question(`Enter new version (current version ${currentVersion}): `, (version) => {
                resolve(version);
            });
        });
    }

    let isValid = false;

    while (!isValid) {
        const userInput = await ask();

        if (/^\d+\.\d+\.\d+$/.test(userInput) && compareVersions(userInput, currentVersion) > 0) {
            newVersion = userInput;
            isValid = true;
        } else {
            console.error('Error: The version must be higher than the current version.');
        }
    }
}

// Function to update the JSON file
async function updateInfoJsonFile() {
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
async function updateMainPluginFile() {
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
async function updatePackageJsonFile() {
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

// Close the readline interface
function closeInterface() {
    rl.close();
}

// Compare two semantic version numbers
function compareVersions(versionA, versionB) {
    const a = versionA.split('.').map(Number);
    const b = versionB.split('.').map(Number);

    for (let i = 0; i < a.length; i++) {
        if (a[i] !== b[i]) {
            return a[i] - b[i];
        }
    }

    return 0;
}

// Call the update functions
promptForNewVersion()
    .then(updateInfoJsonFile)
    .then(updateMainPluginFile)
    .then(updatePackageJsonFile)
    .then(closeInterface)
    .catch((err) => {
        console.error('Error updating files:', err);
        closeInterface();
    });
