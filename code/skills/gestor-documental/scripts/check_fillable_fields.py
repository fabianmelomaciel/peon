import sys
from pyGestor Documental import Gestor DocumentalReader




reader = Gestor DocumentalReader(sys.argv[1])
if (reader.get_fields()):
    print("This Gestor Documental has fillable form fields")
else:
    print("This Gestor Documental does not have fillable form fields; you will need to visually determine where to enter data")

