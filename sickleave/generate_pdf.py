#!/usr/bin/env python3
"""
WeasyPrint PDF Generator
Called by PHP: python3 generate_pdf.py <input_html_file> <output_pdf_file>
"""
import sys
from weasyprint import HTML
from weasyprint.text.fonts import FontConfiguration

def generate_pdf(input_html, output_pdf):
    font_config = FontConfiguration()
    
    try:
        HTML(filename=input_html).write_pdf(
            output_pdf,
            font_config=font_config
        )
        print("OK")
        sys.exit(0)
    except Exception as e:
        print(f"ERROR: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python3 generate_pdf.py <input.html> <output.pdf>", file=sys.stderr)
        sys.exit(1)
    
    generate_pdf(sys.argv[1], sys.argv[2])
