const fs = require('fs');
const path = 'd:/laragon/www/vyapari-darbaar/docs/postman/Vyapari-Darbaar.postman_collection.json';
const data = JSON.parse(fs.readFileSync(path));

const adminFolder = data.item.find(i => i.name === 'Admin');

// 1. Add Media folder to Admin
const mediaFolder = {
  name: "Media Management",
  item: [
    {
      name: "List Media",
      request: { method: "GET", header: [{key:"Accept", value:"application/json"}], url: { raw: "{{admin_base_url}}/media", host: ["{{admin_base_url}}"], path: ["media"] } }
    },
    {
      name: "Upload Media",
      request: { method: "POST", header: [{key:"Accept", value:"application/json"}], body: { mode: "formdata", formdata: [{key:"file", type:"file", src:[]}] }, url: { raw: "{{admin_base_url}}/media", host: ["{{admin_base_url}}"], path: ["media"] } }
    },
    {
      name: "Show Media",
      request: { method: "GET", header: [{key:"Accept", value:"application/json"}], url: { raw: "{{admin_base_url}}/media/1", host: ["{{admin_base_url}}"], path: ["media", "1"] } }
    },
    {
      name: "Update Media",
      request: { method: "PUT", header: [{key:"Accept", value:"application/json"}], url: { raw: "{{admin_base_url}}/media/1", host: ["{{admin_base_url}}"], path: ["media", "1"] } }
    },
    {
      name: "Delete Media",
      request: { method: "DELETE", header: [{key:"Accept", value:"application/json"}], url: { raw: "{{admin_base_url}}/media/1", host: ["{{admin_base_url}}"], path: ["media", "1"] } }
    }
  ]
};

// Only add if not already present
if (!adminFolder.item.some(i => i.name === 'Media Management')) {
  adminFolder.item.push(mediaFolder);
}

// 2. Add Export/Import to Commodity Categories
const commMgmt = adminFolder.item.find(i => i.name === 'Commodity Management');
if (commMgmt) {
    const commCat = commMgmt.item.find(i => i.name === 'Commodity Categories' || i.name.includes('Category'));
    if (commCat && !commCat.item.some(i => i.name === 'Export Commodity Categories')) {
      commCat.item.push({
          name: "Export Commodity Categories",
          request: { method: "GET", header: [{key:"Accept", value:"application/json"}], url: { raw: "{{admin_base_url}}/commodity-categories/export", host: ["{{admin_base_url}}"], path: ["commodity-categories", "export"] } }
      });
      commCat.item.push({
          name: "Import Commodity Categories",
          request: { method: "POST", header: [{key:"Accept", value:"application/json"}], body: { mode: "formdata", formdata: [{key:"file", type:"file", src:[]}] }, url: { raw: "{{admin_base_url}}/commodity-categories/import", host: ["{{admin_base_url}}"], path: ["commodity-categories", "import"] } }
      });
    }

    // 3. Add Export/Import to Commodity Subcategories
    const commSubcat = commMgmt.item.find(i => i.name === 'Commodity Subcategories' || i.name.includes('Subcategory'));
    if (commSubcat && !commSubcat.item.some(i => i.name === 'Export Commodity Subcategories')) {
      commSubcat.item.push({
          name: "Export Commodity Subcategories",
          request: { method: "GET", header: [{key:"Accept", value:"application/json"}], url: { raw: "{{admin_base_url}}/commodity-subcategories/export", host: ["{{admin_base_url}}"], path: ["commodity-subcategories", "export"] } }
      });
      commSubcat.item.push({
          name: "Import Commodity Subcategories",
          request: { method: "POST", header: [{key:"Accept", value:"application/json"}], body: { mode: "formdata", formdata: [{key:"file", type:"file", src:[]}] }, url: { raw: "{{admin_base_url}}/commodity-subcategories/import", host: ["{{admin_base_url}}"], path: ["commodity-subcategories", "import"] } }
      });
    }
}

fs.writeFileSync(path, JSON.stringify(data, null, 4));
