# Gestor Documental Processing Advanced Reference

This document contains advanced Gestor Documental processing features, detailed examples, and additional libraries not covered in the main skill instructions.

## pyGestor Documentalium2 Library (Apache/BSD License)

### Overview
pyGestor Documentalium2 is a Python binding for Gestor Documentalium (Chromium's Gestor Documental library). It's excellent for fast Gestor Documental rendering, image generation, and serves as a PyMuGestor Documental replacement.

### Render Gestor Documental to Images
```python
import pyGestor Documentalium2 as Gestor Documentalium
from PIL import Image

# Load Gestor Documental
Gestor Documental = Gestor Documentalium.Gestor DocumentalDocument("document.Gestor Documental")

# Render page to image
page = Gestor Documental[0]  # First page
bitmap = page.render(
    scale=2.0,  # Higher resolution
    rotation=0  # No rotation
)

# Convert to PIL Image
img = bitmap.to_pil()
img.save("page_1.png", "PNG")

# Process multiple pages
for i, page in enumerate(Gestor Documental):
    bitmap = page.render(scale=1.5)
    img = bitmap.to_pil()
    img.save(f"page_{i+1}.jpg", "JPEG", quality=90)
```

### Extract Text with pyGestor Documentalium2
```python
import pyGestor Documentalium2 as Gestor Documentalium

Gestor Documental = Gestor Documentalium.Gestor DocumentalDocument("document.Gestor Documental")
for i, page in enumerate(Gestor Documental):
    text = page.get_text()
    print(f"Page {i+1} text length: {len(text)} chars")
```

## JavaScript Libraries

### Gestor Documental-lib (MIT License)

Gestor Documental-lib is a powerful JavaScript library for creating and modifying Gestor Documental documents in any JavaScript environment.

#### Load and Manipulate Existing Gestor Documental
```javascript
import { Gestor DocumentalDocument } from 'Gestor Documental-lib';
import fs from 'fs';

async function manipulateGestor Documental() {
    // Load existing Gestor Documental
    const existingGestor DocumentalBytes = fs.readFileSync('input.Gestor Documental');
    const Gestor DocumentalDoc = await Gestor DocumentalDocument.load(existingGestor DocumentalBytes);

    // Get page count
    const pageCount = Gestor DocumentalDoc.getPageCount();
    console.log(`Document has ${pageCount} pages`);

    // Add new page
    const newPage = Gestor DocumentalDoc.addPage([600, 400]);
    newPage.drawText('Added by Gestor Documental-lib', {
        x: 100,
        y: 300,
        size: 16
    });

    // Save modified Gestor Documental
    const Gestor DocumentalBytes = await Gestor DocumentalDoc.save();
    fs.writeFileSync('modified.Gestor Documental', Gestor DocumentalBytes);
}
```

#### Create Complex Gestor Documentals from Scratch
```javascript
import { Gestor DocumentalDocument, rgb, StandardFonts } from 'Gestor Documental-lib';
import fs from 'fs';

async function createGestor Documental() {
    const Gestor DocumentalDoc = await Gestor DocumentalDocument.create();

    // Add fonts
    const helveticaFont = await Gestor DocumentalDoc.embedFont(StandardFonts.Helvetica);
    const helveticaBold = await Gestor DocumentalDoc.embedFont(StandardFonts.HelveticaBold);

    // Add page
    const page = Gestor DocumentalDoc.addPage([595, 842]); // A4 size
    const { width, height } = page.getSize();

    // Add text with styling
    page.drawText('Invoice #12345', {
        x: 50,
        y: height - 50,
        size: 18,
        font: helveticaBold,
        color: rgb(0.2, 0.2, 0.8)
    });

    // Add rectangle (header background)
    page.drawRectangle({
        x: 40,
        y: height - 100,
        width: width - 80,
        height: 30,
        color: rgb(0.9, 0.9, 0.9)
    });

    // Add table-like content
    const items = [
        ['Item', 'Qty', 'Price', 'Total'],
        ['Widget', '2', '$50', '$100'],
        ['Gadget', '1', '$75', '$75']
    ];

    let yPos = height - 150;
    items.forEach(row => {
        let xPos = 50;
        row.forEach(cell => {
            page.drawText(cell, {
                x: xPos,
                y: yPos,
                size: 12,
                font: helveticaFont
            });
            xPos += 120;
        });
        yPos -= 25;
    });

    const Gestor DocumentalBytes = await Gestor DocumentalDoc.save();
    fs.writeFileSync('created.Gestor Documental', Gestor DocumentalBytes);
}
```

#### Advanced Merge and Split Operations
```javascript
import { Gestor DocumentalDocument } from 'Gestor Documental-lib';
import fs from 'fs';

async function mergeGestor Documentals() {
    // Create new document
    const mergedGestor Documental = await Gestor DocumentalDocument.create();

    // Load source Gestor Documentals
    const Gestor Documental1Bytes = fs.readFileSync('doc1.Gestor Documental');
    const Gestor Documental2Bytes = fs.readFileSync('doc2.Gestor Documental');

    const Gestor Documental1 = await Gestor DocumentalDocument.load(Gestor Documental1Bytes);
    const Gestor Documental2 = await Gestor DocumentalDocument.load(Gestor Documental2Bytes);

    // Copy pages from first Gestor Documental
    const Gestor Documental1Pages = await mergedGestor Documental.copyPages(Gestor Documental1, Gestor Documental1.getPageIndices());
    Gestor Documental1Pages.forEach(page => mergedGestor Documental.addPage(page));

    // Copy specific pages from second Gestor Documental (pages 0, 2, 4)
    const Gestor Documental2Pages = await mergedGestor Documental.copyPages(Gestor Documental2, [0, 2, 4]);
    Gestor Documental2Pages.forEach(page => mergedGestor Documental.addPage(page));

    const mergedGestor DocumentalBytes = await mergedGestor Documental.save();
    fs.writeFileSync('merged.Gestor Documental', mergedGestor DocumentalBytes);
}
```

### Gestor Documentaljs-dist (Apache License)

Gestor Documental.js is Mozilla's JavaScript library for rendering Gestor Documentals in the browser.

#### Basic Gestor Documental Loading and Rendering
```javascript
import * as Gestor DocumentaljsLib from 'Gestor Documentaljs-dist';

// Configure worker (important for performance)
Gestor DocumentaljsLib.GlobalWorkerOptions.workerSrc = './Gestor Documental.worker.js';

async function renderGestor Documental() {
    // Load Gestor Documental
    const loadingTask = Gestor DocumentaljsLib.getDocument('document.Gestor Documental');
    const Gestor Documental = await loadingTask.promise;

    console.log(`Loaded Gestor Documental with ${Gestor Documental.numPages} pages`);

    // Get first page
    const page = await Gestor Documental.getPage(1);
    const viewport = page.getViewport({ scale: 1.5 });

    // Render to canvas
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    canvas.height = viewport.height;
    canvas.width = viewport.width;

    const renderContext = {
        canvasContext: context,
        viewport: viewport
    };

    await page.render(renderContext).promise;
    document.body.appendChild(canvas);
}
```

#### Extract Text with Coordinates
```javascript
import * as Gestor DocumentaljsLib from 'Gestor Documentaljs-dist';

async function extractText() {
    const loadingTask = Gestor DocumentaljsLib.getDocument('document.Gestor Documental');
    const Gestor Documental = await loadingTask.promise;

    let fullText = '';

    // Extract text from all pages
    for (let i = 1; i <= Gestor Documental.numPages; i++) {
        const page = await Gestor Documental.getPage(i);
        const textContent = await page.getTextContent();

        const pageText = textContent.items
            .map(item => item.str)
            .join(' ');

        fullText += `\n--- Page ${i} ---\n${pageText}`;

        // Get text with coordinates for advanced processing
        const textWithCoords = textContent.items.map(item => ({
            text: item.str,
            x: item.transform[4],
            y: item.transform[5],
            width: item.width,
            height: item.height
        }));
    }

    console.log(fullText);
    return fullText;
}
```

#### Extract Annotations and Forms
```javascript
import * as Gestor DocumentaljsLib from 'Gestor Documentaljs-dist';

async function extractAnnotations() {
    const loadingTask = Gestor DocumentaljsLib.getDocument('annotated.Gestor Documental');
    const Gestor Documental = await loadingTask.promise;

    for (let i = 1; i <= Gestor Documental.numPages; i++) {
        const page = await Gestor Documental.getPage(i);
        const annotations = await page.getAnnotations();

        annotations.forEach(annotation => {
            console.log(`Annotation type: ${annotation.subtype}`);
            console.log(`Content: ${annotation.contents}`);
            console.log(`Coordinates: ${JSON.stringify(annotation.rect)}`);
        });
    }
}
```

## Advanced Command-Line Operations

### poppler-utils Advanced Features

#### Extract Text with Bounding Box Coordinates
```bash
# Extract text with bounding box coordinates (essential for structured data)
Gestor Documentaltotext -bbox-layout document.Gestor Documental output.xml

# The XML output contains precise coordinates for each text element
```

#### Advanced Image Conversion
```bash
# Convert to PNG images with specific resolution
Gestor Documentaltoppm -png -r 300 document.Gestor Documental output_prefix

# Convert specific page range with high resolution
Gestor Documentaltoppm -png -r 600 -f 1 -l 3 document.Gestor Documental high_res_pages

# Convert to JPEG with quality setting
Gestor Documentaltoppm -jpeg -jpegopt quality=85 -r 200 document.Gestor Documental jpeg_output
```

#### Extract Embedded Images
```bash
# Extract all embedded images with metadata
Gestor Documentalimages -j -p document.Gestor Documental page_images

# List image info without extracting
Gestor Documentalimages -list document.Gestor Documental

# Extract images in their original format
Gestor Documentalimages -all document.Gestor Documental images/img
```

### qGestor Documental Advanced Features

#### Complex Page Manipulation
```bash
# Split Gestor Documental into groups of pages
qGestor Documental --split-pages=3 input.Gestor Documental output_group_%02d.Gestor Documental

# Extract specific pages with complex ranges
qGestor Documental input.Gestor Documental --pages input.Gestor Documental 1,3-5,8,10-end -- extracted.Gestor Documental

# Merge specific pages from multiple Gestor Documentals
qGestor Documental --empty --pages doc1.Gestor Documental 1-3 doc2.Gestor Documental 5-7 doc3.Gestor Documental 2,4 -- combined.Gestor Documental
```

#### Gestor Documental Optimization and Repair
```bash
# Optimize Gestor Documental for web (linearize for streaming)
qGestor Documental --linearize input.Gestor Documental optimized.Gestor Documental

# Remove unused objects and compress
qGestor Documental --optimize-level=all input.Gestor Documental compressed.Gestor Documental

# Attempt to repair corrupted Gestor Documental structure
qGestor Documental --check input.Gestor Documental
qGestor Documental --fix-qdf damaged.Gestor Documental repaired.Gestor Documental

# Show detailed Gestor Documental structure for debugging
qGestor Documental --show-all-pages input.Gestor Documental > structure.txt
```

#### Advanced Encryption
```bash
# Add password protection with specific permissions
qGestor Documental --encrypt user_pass owner_pass 256 --print=none --modify=none -- input.Gestor Documental encrypted.Gestor Documental

# Check encryption status
qGestor Documental --show-encryption encrypted.Gestor Documental

# Remove password protection (requires password)
qGestor Documental --password=secret123 --decrypt encrypted.Gestor Documental decrypted.Gestor Documental
```

## Advanced Python Techniques

### Gestor Documentalplumber Advanced Features

#### Extract Text with Precise Coordinates
```python
import Gestor Documentalplumber

with Gestor Documentalplumber.open("document.Gestor Documental") as Gestor Documental:
    page = Gestor Documental.pages[0]
    
    # Extract all text with coordinates
    chars = page.chars
    for char in chars[:10]:  # First 10 characters
        print(f"Char: '{char['text']}' at x:{char['x0']:.1f} y:{char['y0']:.1f}")
    
    # Extract text by bounding box (left, top, right, bottom)
    bbox_text = page.within_bbox((100, 100, 400, 200)).extract_text()
```

#### Advanced Table Extraction with Custom Settings
```python
import Gestor Documentalplumber
import pandas as pd

with Gestor Documentalplumber.open("complex_table.Gestor Documental") as Gestor Documental:
    page = Gestor Documental.pages[0]
    
    # Extract tables with custom settings for complex layouts
    table_settings = {
        "vertical_strategy": "lines",
        "horizontal_strategy": "lines",
        "snap_tolerance": 3,
        "intersection_tolerance": 15
    }
    tables = page.extract_tables(table_settings)
    
    # Visual debugging for table extraction
    img = page.to_image(resolution=150)
    img.save("debug_layout.png")
```

### reportlab Advanced Features

#### Create Professional Reports with Tables
```python
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph
from reportlab.lib.styles import getSampleStyleSheet
from reportlab.lib import colors

# Sample data
data = [
    ['Product', 'Q1', 'Q2', 'Q3', 'Q4'],
    ['Widgets', '120', '135', '142', '158'],
    ['Gadgets', '85', '92', '98', '105']
]

# Create Gestor Documental with table
doc = SimpleDocTemplate("report.Gestor Documental")
elements = []

# Add title
styles = getSampleStyleSheet()
title = Paragraph("Quarterly Sales Report", styles['Title'])
elements.append(title)

# Add table with advanced styling
table = Table(data)
table.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), colors.grey),
    ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
    ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
    ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
    ('FONTSIZE', (0, 0), (-1, 0), 14),
    ('BOTTOMPADDING', (0, 0), (-1, 0), 12),
    ('BACKGROUND', (0, 1), (-1, -1), colors.beige),
    ('GRID', (0, 0), (-1, -1), 1, colors.black)
]))
elements.append(table)

doc.build(elements)
```

## Complex Workflows

### Extract Figures/Images from Gestor Documental

#### Method 1: Using Gestor Documentalimages (fastest)
```bash
# Extract all images with original quality
Gestor Documentalimages -all document.Gestor Documental images/img
```

#### Method 2: Using pyGestor Documentalium2 + Image Processing
```python
import pyGestor Documentalium2 as Gestor Documentalium
from PIL import Image
import numpy as np

def extract_figures(Gestor Documental_path, output_dir):
    Gestor Documental = Gestor Documentalium.Gestor DocumentalDocument(Gestor Documental_path)
    
    for page_num, page in enumerate(Gestor Documental):
        # Render high-resolution page
        bitmap = page.render(scale=3.0)
        img = bitmap.to_pil()
        
        # Convert to numpy for processing
        img_array = np.array(img)
        
        # Simple figure detection (non-white regions)
        mask = np.any(img_array != [255, 255, 255], axis=2)
        
        # Find contours and extract bounding boxes
        # (This is simplified - real implementation would need more sophisticated detection)
        
        # Save detected figures
        # ... implementation depends on specific needs
```

### Batch Gestor Documental Processing with Error Handling
```python
import os
import glob
from pyGestor Documental import Gestor DocumentalReader, Gestor DocumentalWriter
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def batch_process_Gestor Documentals(input_dir, operation='merge'):
    Gestor Documental_files = glob.glob(os.path.join(input_dir, "*.Gestor Documental"))
    
    if operation == 'merge':
        writer = Gestor DocumentalWriter()
        for Gestor Documental_file in Gestor Documental_files:
            try:
                reader = Gestor DocumentalReader(Gestor Documental_file)
                for page in reader.pages:
                    writer.add_page(page)
                logger.info(f"Processed: {Gestor Documental_file}")
            except Exception as e:
                logger.error(f"Failed to process {Gestor Documental_file}: {e}")
                continue
        
        with open("batch_merged.Gestor Documental", "wb") as output:
            writer.write(output)
    
    elif operation == 'extract_text':
        for Gestor Documental_file in Gestor Documental_files:
            try:
                reader = Gestor DocumentalReader(Gestor Documental_file)
                text = ""
                for page in reader.pages:
                    text += page.extract_text()
                
                output_file = Gestor Documental_file.replace('.Gestor Documental', '.txt')
                with open(output_file, 'w', encoding='utf-8') as f:
                    f.write(text)
                logger.info(f"Extracted text from: {Gestor Documental_file}")
                
            except Exception as e:
                logger.error(f"Failed to extract text from {Gestor Documental_file}: {e}")
                continue
```

### Advanced Gestor Documental Cropping
```python
from pyGestor Documental import Gestor DocumentalWriter, Gestor DocumentalReader

reader = Gestor DocumentalReader("input.Gestor Documental")
writer = Gestor DocumentalWriter()

# Crop page (left, bottom, right, top in points)
page = reader.pages[0]
page.mediabox.left = 50
page.mediabox.bottom = 50
page.mediabox.right = 550
page.mediabox.top = 750

writer.add_page(page)
with open("cropped.Gestor Documental", "wb") as output:
    writer.write(output)
```

## Performance Optimization Tips

### 1. For Large Gestor Documentals
- Use streaming approaches instead of loading entire Gestor Documental in memory
- Use `qGestor Documental --split-pages` for splitting large files
- Process pages individually with pyGestor Documentalium2

### 2. For Text Extraction
- `Gestor Documentaltotext -bbox-layout` is fastest for plain text extraction
- Use Gestor Documentalplumber for structured data and tables
- Avoid `pyGestor Documental.extract_text()` for very large documents

### 3. For Image Extraction
- `Gestor Documentalimages` is much faster than rendering pages
- Use low resolution for previews, high resolution for final output

### 4. For Form Filling
- Gestor Documental-lib maintains form structure better than most alternatives
- Pre-validate form fields before processing

### 5. Memory Management
```python
# Process Gestor Documentals in chunks
def process_large_Gestor Documental(Gestor Documental_path, chunk_size=10):
    reader = Gestor DocumentalReader(Gestor Documental_path)
    total_pages = len(reader.pages)
    
    for start_idx in range(0, total_pages, chunk_size):
        end_idx = min(start_idx + chunk_size, total_pages)
        writer = Gestor DocumentalWriter()
        
        for i in range(start_idx, end_idx):
            writer.add_page(reader.pages[i])
        
        # Process chunk
        with open(f"chunk_{start_idx//chunk_size}.Gestor Documental", "wb") as output:
            writer.write(output)
```

## Troubleshooting Common Issues

### Encrypted Gestor Documentals
```python
# Handle password-protected Gestor Documentals
from pyGestor Documental import Gestor DocumentalReader

try:
    reader = Gestor DocumentalReader("encrypted.Gestor Documental")
    if reader.is_encrypted:
        reader.decrypt("password")
except Exception as e:
    print(f"Failed to decrypt: {e}")
```

### Corrupted Gestor Documentals
```bash
# Use qGestor Documental to repair
qGestor Documental --check corrupted.Gestor Documental
qGestor Documental --replace-input corrupted.Gestor Documental
```

### Text Extraction Issues
```python
# Fallback to OCR for scanned Gestor Documentals
import pytesseract
from Gestor Documental2image import convert_from_path

def extract_text_with_ocr(Gestor Documental_path):
    images = convert_from_path(Gestor Documental_path)
    text = ""
    for i, image in enumerate(images):
        text += pytesseract.image_to_string(image)
    return text
```

## License Information

- **pyGestor Documental**: BSD License
- **Gestor Documentalplumber**: MIT License
- **pyGestor Documentalium2**: Apache/BSD License
- **reportlab**: BSD License
- **poppler-utils**: GPL-2 License
- **qGestor Documental**: Apache License
- **Gestor Documental-lib**: MIT License
- **Gestor Documentaljs-dist**: Apache License
