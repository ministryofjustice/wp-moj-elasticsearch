const AWS = require('aws-sdk');
const s3 = new AWS.S3();

exports.handler = async function(event) {
    let bucketName = process.env.bucketName;
    let keyName = getKeyName(event.folder, event.filename);
    let fileSize = 0;

    let params = { Bucket: bucketName, Key: keyName, Body: JSON.stringify(event.data) };

    await s3.putObject(params, function (err, data) {
        if (err) { console.log(err) }
        else {
            console.log("Successfully saved object to " + bucketName + "/" + keyName);
            fileSize = sizeOf(keyName, bucketName);
        }
    }).promise().then( res => { return fileSize });

    return fileSize;
}

async function sizeOf(key, bucket){
    let size = 0;
    await s3.headObject({ Key: key, Bucket: bucket }, function(err, data){
        if (err) {
            console.log(err);
        }
        else {
            console.log("Retrived the filesize for " + key);
            size = data.ContentLength;
        }
    }).promise().then( res => { return size });

    return size;
}

function getKeyName(folder, filename) {
    return folder + '/' + filename;
}
