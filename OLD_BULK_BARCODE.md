To switch back to the old version in the future, you can either:

Rename the files:


# Backup current version
mv resources/views/products/bulk-print-barcode.blade.php resources/views/products/bulk-print-barcode-new.blade.php

# Restore old version
mv resources/views/products/bulk-print-barcode-backup.blade.php resources/views/products/bulk-print-barcode.blade.php
Or update the controller to point to the backup view:


// In bulkPrintBarcodeForm() method
return view('products.bulk-print-barcode-backup', compact('products'));