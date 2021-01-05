const AWS = require('aws-sdk')
const s3 = new AWS.S3()

exports.handler = async function (event) {
    let bucketName = process.env.bucketName
    let keyName = getKeyName(event.folder, event.filename)
    let fileData = ''

    event.production || console.log('1.0) Getting S3 fileData...')

    try {
        await s3.getObject({ Bucket: bucketName, Key: keyName }, function (err, data) {
            if (err) {
                var message = '1.1) ' + keyName + ' does not exist in this bucket:' + bucketName + '. Create it...'
                event.production || console.log(message)
            } else {
                // our intention is to append, convert fileData to a string
                fileData = data.Body.toString('utf-8')
            }
        }).promise().then(res => { return fileData })
    } catch (err) {
        fileData = ''
    }

    if (!event.production && fileData.length > 10) {
        console.log('1.1) ... SUCCESS: data length = ' + fileData.length)
    }

    event.production || console.log('2.0) Appending incoming JSON...')
    fileData = fileData + event.data.replace('\n', '\n') + '\n'

    let params = { Bucket: bucketName, Key: keyName, Body: fileData }

    await s3.putObject(params, function (err, data) {
        if (err) {
            console.log(err)
        } else {
            event.production || console.log('3.0) Successfully saved object to ' + bucketName + '/' + keyName)
        }
    }).promise()
}

function getKeyName (folder, filename) {
    return folder + '/' + filename
}
