// main.js
const fs = require('fs').promises;
const path = require('path');
const { promptForNewVersion, promptForChanges, closeInterface } = require('./modules/userPrompts');


const infoJsonFile = path.join(__dirname, '..', 'info.json');
const mainPluginFile = path.join(__dirname, '..', 'prikr-image-offloader.php');
const packageJsonFile = path.join(__dirname, '..', 'package.json');
const changelogFile = path.join(__dirname, '..', 'changelog.html');


// Function to update the JSON file
async function updateInfoJsonFile(newVersion) {
    try {
        const currentDate = new Date().toISOString().slice(0, 19).replace("T", " "); // Get today's date in the format "YYYY-MM-DD HH:mm:ss"
        const data = await fs.readFile(infoJsonFile, 'utf8');
        const json = JSON.parse(data);
        json.version = newVersion;
        json.last_updated = currentDate;
        await fs.writeFile(infoJsonFile, JSON.stringify(json, null, 2), 'utf8');
        console.log(`Version updated to ${newVersion} and last_updated to ${currentDate} in JSON file`);
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

// Function to write changes to changelog.html
async function writeChangesToChangelog(version, changes) {
    try {
        const currentDate = new Date().toISOString().slice(0, 10); // Get today's date in the format "YYYY-MM-DD"

        let changelogContent = await fs.readFile(changelogFile, 'utf8');

        const changelogEntry = `<li>
                                    <h4><span class="version">${version}</span>    <span class="date">${currentDate}</span></h4>
                                    <ul>
                                        ${changes.map(change => `<li>${change}</li>`).join('\n')}
                                    </ul>
                                </li>`;

        if (!changelogContent.includes('<ul>')) {
            // If <ul> tag is not present, add it along with the first entry
            changelogContent = `<h1>Changelog</h1>\n\n<ul>\n    ${changelogEntry}\n</ul>`;
        } else {
            // Replace the <ul> tag with the new version and changes
            changelogContent = changelogContent.replace('<ul>', `<ul>\n    ${changelogEntry}`);
        }

        await fs.writeFile(changelogFile, changelogContent, 'utf8');
        console.log(`Changes written to changelog.html for version ${version}`);
    } catch (err) {
        console.error('Error writing changes to changelog.html:', err);
    }
}



async function main() {
    try {
        const newVersion = await promptForNewVersion(packageJsonFile);

        const changes = await promptForChanges();
        console.log('Changes:', changes);

        await Promise.all([
            updateInfoJsonFile(newVersion),
            updateMainPluginFile(newVersion),
            updatePackageJsonFile(newVersion),
            writeChangesToChangelog(newVersion, changes),
        ]);

        closeInterface();
    } catch (err) {
        console.error('Error updating files:', err);
        closeInterface();
    }
}

// Call the main function
main();