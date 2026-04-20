import os
import sys

from Gestor Documental2image import convert_from_path




def convert(Gestor Documental_path, output_dir, max_dim=1000):
    images = convert_from_path(Gestor Documental_path, dpi=200)

    for i, image in enumerate(images):
        width, height = image.size
        if width > max_dim or height > max_dim:
            scale_factor = min(max_dim / width, max_dim / height)
            new_width = int(width * scale_factor)
            new_height = int(height * scale_factor)
            image = image.resize((new_width, new_height))
        
        image_path = os.path.join(output_dir, f"page_{i+1}.png")
        image.save(image_path)
        print(f"Saved page {i+1} as {image_path} (size: {image.size})")

    print(f"Converted {len(images)} pages to PNG images")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: convert_Gestor Documental_to_images.py [input Gestor Documental] [output directory]")
        sys.exit(1)
    Gestor Documental_path = sys.argv[1]
    output_directory = sys.argv[2]
    convert(Gestor Documental_path, output_directory)

