const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, 'docs/postman/Vyapari-Darbaar.postman_collection.json');
let collection = JSON.parse(fs.readFileSync(filePath, 'utf8'));

const targetNames = [
    "Create Commodity Category",
    "Update Commodity Category",
    "Create Commodity Subcategory",
    "Update Commodity Subcategory"
];

function processItem(item) {
    if (item.name && targetNames.includes(item.name)) {
        console.log(`Processing: ${item.name}`);
        
        // Remove Content-Type application/json header
        if (item.request.header) {
            item.request.header = item.request.header.filter(h => h.key !== 'Content-Type');
        }

        let isUpdate = item.name.includes("Update");
        
        // Only convert if it's currently raw JSON
        if (item.request.body && item.request.body.mode === 'raw') {
            let rawJson = JSON.parse(item.request.body.raw);
            let formdata = [];
            
            if (isUpdate) {
                formdata.push({ key: "_method", value: "PUT", type: "text" });
                item.request.method = "POST"; // Change method to POST for form-data PUT simulation
            }

            for (let [key, value] of Object.entries(rawJson)) {
                if (key === 'image_url' || key === 'media_id') continue; // skip these, we will use file upload
                
                let valStr = value;
                if (typeof value === 'boolean') {
                    valStr = value ? "1" : "0";
                } else if (value === null) {
                    valStr = "";
                } else {
                    valStr = String(value);
                }

                formdata.push({
                    key: key,
                    value: valStr,
                    type: "text"
                });
            }
            
            // Add image file field
            formdata.push({
                key: "image",
                type: "file",
                src: []
            });

            item.request.body.mode = 'formdata';
            item.request.body.formdata = formdata;
            delete item.request.body.raw;
        }
    }
    
    if (item.item) {
        item.item.forEach(processItem);
    }
}

collection.item.forEach(processItem);

fs.writeFileSync(filePath, JSON.stringify(collection, null, 4));
console.log('Collection updated successfully.');
