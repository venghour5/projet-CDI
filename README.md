# Notice API

Les paramètres ne sont pas sauvegardés après redémarrage du programme.

Changement de la couleur : 
	Valeur par défaut : (255, 255, 255).
	Il est possible de changer la couleur lorsqu’elle est fixe ou en mode sections.
	Elle sera gardée si la strip change de mode, mais sera réinitialisée si la carte redémarre. 
	http://<IP>/couleurLED?r=[valeur rouge (0-255)]&g=[valeur verte (0-255)]&b=[valeur bleue (0-255)]
	Ex: http://<IP>/couleurLED?r=255&g=0&b=67

Changement de la vitesse de changement de couleur pendant le mode “arc-en-ciel” : 
	Valeur par défaut : 1
	Techniquement la valeur n’a pas de limite, mais à partir de 255 la led peut rester noire ou blanche, car le hue arrivée à 255 ou au-dessus, et est alors remis tout de suite à zéro, pour tout de suite repartir à 255 ou au-dessus. Encore, au delà de 10, la vitesse devient rapide pour les yeux et peut faire mal. 
	http://<IP>/vitesseLED?v=[valeur vitesse]
	Ex: http://<IP>/vitesseLED?v=10

Activation ou désactivation du mode “arc-en-ciel” : 
	Le mode “arc-en-ciel” est utile si on doit tester la strip, pour voir si les couleurs marchent bien et s’il y a des problèmes de courant. 
	http://<IP>/arcencielLED
	Il l’active ou le désactive tout seul, pas besoin d’argument supplémentaire.

Mode police
	http://<IP>/policeLED

Gestion des sections de LEDs
	Valeurs par défaut : nombre de sections (1), leds par section (60), section actuelle (0)
	Les sections sont ce qui divise la strip en plusieurs parties. Chaque section a un certain nombre de LEDs.
	La section actuelle est la section qui est actuellement allumée. Si elle est de 0, c’est la première section.
	http://<IP>/sectionLED?n=[nombre de sections]&m=[nombre de leds par section]&s=[section actuelle]
	
Options
	Les options ne sont pas très utiles et sont plutôt pour du débogage / amusement.
	enleverAnciennesLEDs : Par défaut (true). Retire les anciennes LEDs allumées après changement de section, par exemple. S’active / se désactive avec http://<IP>/enleverAnciennesLEDs
