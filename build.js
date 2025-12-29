const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

const moduleName = 'channable';
const packageJson = JSON.parse(fs.readFileSync('./package.json', 'utf8'));
const version = packageJson.version;
const outputFileName = `${moduleName}-v${version}.zip`;

if (fs.existsSync(outputFileName)) {
  fs.unlinkSync(outputFileName);
  console.log(`Removed existing ${outputFileName}`);
}

const output = fs.createWriteStream(outputFileName);
const archive = archiver('zip', {
  zlib: { level: 9 }
});

output.on('close', function() {
  console.log(`\n✓ ${outputFileName} created successfully!`);
  console.log(`  Total bytes: ${archive.pointer()}`);
  console.log(`  Size: ${(archive.pointer() / 1024 / 1024).toFixed(2)} MB`);
});

archive.on('error', function(err) {
  throw err;
});

archive.pipe(output);

const filesToExclude = [
  'node_modules',
  '.git',
  '.env',
  'package.json',
  'package-lock.json',
  'build.js',
  `${moduleName}-v${version}.zip`,
  '.gitignore',
  'Readme.md'
];

console.log(`Creating ${outputFileName}...`);
console.log(`Excluding: ${filesToExclude.join(', ')}\n`);

const addFilesToArchive = (dir, prefix = '') => {
  const files = fs.readdirSync(dir);

  files.forEach(file => {
    if (filesToExclude.includes(file) || file.endsWith('.zip')) {
      return;
    }

    const filePath = path.join(dir, file);
    const stat = fs.statSync(filePath);
    const archivePath = path.join(moduleName, prefix, file);

    if (stat.isDirectory()) {
      addFilesToArchive(filePath, path.join(prefix, file));
    } else {
      console.log(`  Adding: ${archivePath}`);
      archive.file(filePath, { name: archivePath });
    }
  });
};

addFilesToArchive('.');

archive.finalize();
