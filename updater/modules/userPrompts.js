// userPrompts.js
const fs = require('fs').promises;
const readline = require('readline');

const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

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

// Utility function to prompt the user for input
async function ask(question) {
    return new Promise((resolve) => {
        rl.question(question, (answer) => {
            resolve(answer);
        });
    });
}

// Function to prompt the user for the new version
async function promptForNewVersion(packageJsonFile) {
    const currentVersion = await getCurrentVersionFromFile(packageJsonFile) || '0.0.1';

    let isValid = false;
    let newVersion;

    while (!isValid) {
        const userInput = await ask(`Enter new version (current version ${currentVersion}): `);

        if (/^\d+\.\d+\.\d+$/.test(userInput) && compareVersions(userInput, currentVersion) > 0) {
            newVersion = userInput;
            isValid = true;
        } else {
            console.error('Error: The version must be higher than the current version.');
        }
    }

    return newVersion;
}

// Function to prompt the user for changes
async function promptForChanges() {
    const changes = [];

    while (true) {
        const change = await ask('Enter a change description (type "exit" to stop asking): ');

        if (change.trim() === 'exit') {
            break;
        }

        if (change.trim() !== '') {
            changes.push(change);
        }
    }

    return changes;
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

module.exports = {
    promptForNewVersion,
    promptForChanges,
    closeInterface,
};
