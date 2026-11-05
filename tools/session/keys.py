import json,glob,sys
T={
"Unknown duty rating or series. Please reload the builder and choose again.":("Clasificación de servicio o serie desconocida. Recargue el configurador y vuelva a elegir.","Classe de service ou série inconnue. Rechargez le configurateur et choisissez à nouveau."),
"That pre-made driveshaft is not available.":("Ese cardán prefabricado no está disponible.","Cet arbre de transmission préfabriqué n'est pas disponible."),
"Please choose a valid front application.":("Elija una aplicación delantera válida.","Choisissez une application avant valide."),
"Please choose a valid rear application.":("Elija una aplicación trasera válida.","Choisissez une application arrière valide."),
"Custom driveshaft":("Cardán a medida","Arbre de transmission sur mesure"),
"Pre-Made":("Prefabricado","Préfabriqué"),
"Complete driveshafts":("Cardanes completos","Arbres de transmission complets"),
"Complete driveshaft":("Cardán completo","Arbre de transmission complet"),
"fits :vehicle":("para :vehicle","pour :vehicle"),
"Part number":("Número de parte","Numéro de pièce"),
"Interchange matches":("Equivalencias","Correspondances"),
"Replaces":("Reemplaza","Remplace"),
"Series":("Serie","Série"),"Brand":("Marca","Marque"),"Notes":("Notas","Notes"),
"Product not found":("Producto no encontrado","Produit introuvable"),
"This part is priced on request. Call (813) 524-6491 for a quote.":("El precio de esta pieza es a consultar. Llame al (813) 524-6491 para una cotización.","Le prix de cette pièce est sur demande. Appelez le (813) 524-6491 pour un devis."),
"Part not found.":("Pieza no encontrada.","Pièce introuvable."),
"Ships from our supplier":("Se envía desde nuestro proveedor","Expédié par notre fournisseur"),
"Not on our Florida shelf right now; our supplier has it. Call (813) 524-6491 for a ship date.":("No está en nuestro almacén de Florida ahora; nuestro proveedor la tiene. Llame al (813) 524-6491 para conocer la fecha de envío.","Pas en stock en Floride pour le moment ; notre fournisseur l'a. Appelez le (813) 524-6491 pour une date d'expédition."),
"Get a quote":("Pedir cotización","Demander un devis"),
"Call (813) 524-6491":("Llame al (813) 524-6491","Appelez le (813) 524-6491"),
"Call for price":("Precio a consultar","Prix sur demande"),
"Add :sku to cart":("Agregar :sku al carrito","Ajouter :sku au panier"),
"Add":("Agregar","Ajouter"),"Pic":("Foto","Photo"),"Part #":("N.º de parte","N° de pièce"),"Price":("Precio","Prix"),
"Yes":("Sí","Oui"),"No":("No","Non"),
"Search: :q":("Búsqueda: :q","Recherche : :q"),
"Search the catalog":("Buscar en el catálogo","Rechercher dans le catalogue"),
"Search Universal Driveshafts by part number, name or series. Results grouped by category with specs, prices and stock.":("Busque en Universal Driveshafts por número de parte, nombre o serie. Resultados agrupados por categoría con especificaciones, precios y stock.","Recherchez chez Universal Driveshafts par numéro de pièce, nom ou série. Résultats groupés par catégorie avec caractéristiques, prix et stock."),
"Show all :n in :cat":("Ver los :n en :cat","Voir les :n dans :cat"),
"+ :amount core":("+ :amount de casco","+ :amount de consigne"),
"Description":("Descripción","Description"),"Fits":("Compatible con","Compatible avec"),"Weight":("Peso","Poids"),
"Showing :n of :total.":("Mostrando :n de :total.","Affichage de :n sur :total."),
"Filter by :name":("Filtrar por :name","Filtrer par :name"),
"All Small Parts":("Todas las piezas pequeñas","Toutes les petites pièces"),
"Complete driveshafts by vehicle":("Cardanes completos por vehículo","Arbres de transmission complets par véhicule"),
"Choose your make, year and model and we narrow the catalog to the complete driveshaft that fits. 4,500+ driveshafts, 1955 to 2026.":("Elija marca, año y modelo y reducimos el catálogo al cardán completo que le sirve. Más de 4,500 cardanes, de 1955 a 2026.","Choisissez la marque, l'année et le modèle et nous réduisons le catalogue à l'arbre complet qui convient. Plus de 4 500 arbres, de 1955 à 2026."),
"Every complete driveshaft we stock, listed by UDS part number with price, description and the vehicles it fits. Or find yours by year, make and model.":("Todos los cardanes completos que tenemos, por número de parte UDS con precio, descripción y los vehículos compatibles. O encuentre el suyo por año, marca y modelo.","Tous les arbres complets que nous stockons, par numéro de pièce UDS avec prix, description et véhicules compatibles. Ou trouvez le vôtre par année, marque et modèle."),
":pn Driveshaft":("Cardán :pn","Arbre de transmission :pn"),
"Complete :pieces driveshaft :pn":("Cardán completo :pieces :pn","Arbre de transmission complet :pieces :pn"),
"Complete driveshaft :pn":("Cardán completo :pn","Arbre de transmission complet :pn"),
"for :apps":("para :apps","pour :apps"),
"and :n more applications":("y :n aplicaciones más","et :n autres applications"),
"Assembled and balanced, ships from Florida.":("Ensamblado y balanceado, se envía desde Florida.","Assemblé et équilibré, expédié depuis la Floride."),
"one-piece":("de una pieza","monobloc"),"two-piece":("de dos piezas","en deux parties"),"three-piece":("de tres piezas","en trois parties"),
"Adding…":("Agregando…","Ajout…"),"Added ✓":("Agregado ✓","Ajouté ✓"),"Quantity":("Cantidad","Quantité"),
"Session expired — reload the page and try again.":("La sesión expiró: recargue la página e inténtelo de nuevo.","Session expirée — rechargez la page et réessayez."),
"Too many tries — wait a minute and try again.":("Demasiados intentos: espere un minuto e inténtelo de nuevo.","Trop d'essais — patientez une minute et réessayez."),
"Could not add to cart. Please try again.":("No se pudo agregar al carrito. Inténtelo de nuevo.","Impossible d'ajouter au panier. Veuillez réessayer."),
"Network error. Please try again.":("Error de red. Inténtelo de nuevo.","Erreur réseau. Veuillez réessayer."),
"Could not load the parts. Please try again.":("No se pudieron cargar las piezas. Inténtelo de nuevo.","Impossible de charger les pièces. Veuillez réessayer."),
"Remove from wishlist":("Quitar de la lista de deseos","Retirer de la liste d'envies"),
"Add to wishlist":("Agregar a la lista de deseos","Ajouter à la liste d'envies"),
}
for i,loc in enumerate(['es','fr']):
    merged={}
    for f in glob.glob(f'lang/i18n/*/{loc}.json'):
        merged.update(json.load(open(f)))
    path=f'lang/i18n/catalog/{loc}.json'
    cat=json.load(open(path))
    added=0
    for k,v in T.items():
        if k not in merged:
            cat[k]=v[i]; added+=1
    json.dump(cat,open(path,'w'),ensure_ascii=False,indent=4)
    open(path,'a').write('\n')
    print(loc,'added',added)
