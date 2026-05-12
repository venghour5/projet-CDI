Pour l’utilisation des microcontrôleurs, nous avons créé un programme en C++ sur Arduino qui écoutera des requêtes HTTP et réagira en fonction de ce qui lui a été envoyé. Les fonctions accessibles à l’API sont détaillées ci-dessous. Les paramètres donnés, tels que le changement de la couleur ou des sections, ne sont pas gardés en mémoire et seront alors réinitialisés lors d’un redémarrage du module.
	Après chaque appel de fonction, le programme renvoie un message pour confirmer la réception de la requête. 
	
Changement de la couleur
	La valeur par défaut de la couleur est RGB(255, 255, 255)
	Cette fonction peut être appelée à l’aide de “http://<IP>/couleurLED?r=[valeur rouge (0-255)]&g=[valeur verte (0-255)]&b=[valeur bleue (0-255)]”
	Elle permet le changement de la couleur de la section actuellement sélectionnée. Par défaut, la section est paramétrée en une seule section totale, donnant ainsi l’impression qu’on change la couleur de l’entièreté de la LED.
Activation ou désactivation du mode “arc-en-ciel”
	 Ce mode permet à l’origine de tester la bande LED, afin de vérifier les couleurs qu’elle affiche et leur visibilité dans l’environnement. Il est désactivé par défaut. Il a été également utile lors de tests pour vérifier la puissance envoyée à la LED. 
	Cette fonction peut être appelée à l’aide de “http://<IP>/arcencielLED”. Aucune valeur n’est à spécifier, le mode s’activera ou se désactivera tout seul. 
Changement de la vitesse du mode “arc-en-ciel”
	Cette fonction permet de modifier la vitesse du mode précédent, permettant ainsi plus de tests colorimétriques. 
	Cette fonction peut être appelée à l’aide de “http://<IP>/vitesseLED?v=[valeur vitesse]”.
	La valeur donnée n’a théoriquement aucune limite, mais au-delà de 10 la bande peut avoir des effets négatifs ou non voulus. 
Gestion des sections de LEDs
	Les sections permettent à la bande d’être séparée en plusieurs zones afin d’allumer un endroit spécifique de la bande. C’est la stratégie principale qui sera utilisée afin d’allumer qu’une certaine partie de l’étagère. 
	La valeur par défaut est : nombre de sections = 1, LEDs par section = 60, section actuelle = 0.
	Le nombre de sections définit en combien de parties la bande va être divisée.
	Le nombre de LEDs par section définit la taille de chaque section est calculé automatiquement, par rapport au nombre total de leds de la bande défini par LEDS_TOTAL. S’il doit changer, il faut reprogrammer la carte. Il n’est pas censé changer par défaut.
	La section actuelle correspond à la section actuellement sélectionnée par le programme. Bien qu’elle soit pas visuellement distinguable des autres, c’est celle-ci qui verra sa couleur changer lorsque la fonction couleurLED est appelée. 
	Cette fonction peut être appelée à l’aide de “http://<IP>/sectionLED?n=[nombre de sections]&s=[section actuelle]”.
	Lorsque nous voulons changer de section sur laquelle nous travaillons, nous devons toujours spécifier quand même les deux autres paramètres, bien qu’ils puissent rester les mêmes.
Mise à jour de la luminosité
	Permet de mettre à jour la luminosité globale de la strip (par défaut à 255, au maximum. Minimum 0).
Cette fonction peut être appelée à l’aide de “http://<IP>/luminositeLED?l=[luminosite]”.
Autres options
	Ces autres options ne sont pas spécialement utiles au projet mais permettent une configuration plus avancée de la bande.
enleverAnciennesLEDs
	Cette option retire les anciennes LEDs allumées après changement de section. Elle est par défaut mise à “true”.
	En d’autres termes, lorsqu’une section est selectionnée, puis changée de couleur, si nous changons encore de sections après, la précédente s’éteint, rendant alors seulement la dernière section selectionnée visible. Si cette option est mise à “false”, il se passera alors l’inverse : les sections précédemment allumées et maintenant désélectionnées restent allumées. 
	Cette fonction peut être appelée à l’aide de “http://<IP>/enleverAnciennesLEDs”.
