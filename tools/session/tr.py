import json, glob, collections
T = {
"Keep me signed in": ("Mantener la sesión iniciada", "Rester connecté"),
"Sign out": ("Cerrar sesión", "Se déconnecter"),
"Order not found.": ("Pedido no encontrado.", "Commande introuvable."),
"None of the parts on that order can be reordered online. Call (813) 524-6491.": ("Ninguna de las piezas de ese pedido se puede volver a pedir en línea. Llame al (813) 524-6491.", "Aucune des pièces de cette commande ne peut être recommandée en ligne. Appelez le (813) 524-6491."),
"Too many wrong codes. For your security, codes to this address are paused for 24 hours. Call (813) 524-6491 if you need help.": ("Demasiados códigos incorrectos. Por su seguridad, los códigos a esta dirección quedan en pausa 24 horas. Llame al (813) 524-6491 si necesita ayuda.", "Trop de codes erronés. Pour votre sécurité, l'envoi de codes à cette adresse est suspendu pendant 24 heures. Appelez le (813) 524-6491 si vous avez besoin d'aide."),
"Too many codes were sent to this address today. Try again tomorrow.": ("Se enviaron demasiados códigos a esta dirección hoy. Inténtelo de nuevo mañana.", "Trop de codes ont été envoyés à cette adresse aujourd'hui. Réessayez demain."),
"Enter this code to confirm it is you before changing the sign-in details of your Universal Driveshafts account.": ("Ingrese este código para confirmar que es usted antes de cambiar los datos de acceso de su cuenta de Universal Driveshafts.", "Saisissez ce code pour confirmer qu'il s'agit bien de vous avant de modifier les identifiants de votre compte Universal Driveshafts."),
"Reset your Universal Driveshafts password": ("Restablezca su contraseña de Universal Driveshafts", "Réinitialisez votre mot de passe Universal Driveshafts"),
"Hi :name, someone asked to reset the password for this e-mail address at universaldriveshafts.com. If that was you, use the button below. The link works for 60 minutes.": ("Hola :name, alguien pidió restablecer la contraseña de esta dirección de correo en universaldriveshafts.com. Si fue usted, use el botón de abajo. El enlace funciona durante 60 minutos.", "Bonjour :name, quelqu'un a demandé la réinitialisation du mot de passe de cette adresse e-mail sur universaldriveshafts.com. Si c'était vous, utilisez le bouton ci-dessous. Le lien est valable 60 minutes."),
"there": ("", ""),
"Account security": ("Seguridad de la cuenta", "Sécurité du compte"),
"Choose a new password": ("Elija una nueva contraseña", "Choisissez un nouveau mot de passe"),
"If you did not ask for this, ignore this e-mail. Your password stays as it is.": ("Si no lo pidió, ignore este correo. Su contraseña no cambia.", "Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail. Votre mot de passe reste inchangé."),
"We sent a 6-digit code to :email.": ("Enviamos un código de 6 dígitos a :email.", "Nous avons envoyé un code à 6 chiffres à :email."),
"Enter the code we sent to :email (use \"Send code\" first).": ("Ingrese el código que enviamos a :email (primero use \"Enviar código\").", "Saisissez le code envoyé à :email (utilisez d'abord « Envoyer le code »)."),
"Password updated. Any other devices signed in to your account were signed out.": ("Contraseña actualizada. Se cerró la sesión en cualquier otro dispositivo conectado a su cuenta.", "Mot de passe mis à jour. Les autres appareils connectés à votre compte ont été déconnectés."),
"This account is already an approved business account.": ("Esta cuenta ya es una cuenta comercial aprobada.", "Ce compte est déjà un compte professionnel approuvé."),
"Your application is already under review. We will e-mail you.": ("Su solicitud ya está en revisión. Le escribiremos por correo.", "Votre demande est déjà en cours d'examen. Nous vous écrirons par e-mail."),
"Code sent to :email": ("Código enviado a :email", "Code envoyé à :email"),
"Send again": ("Enviar de nuevo", "Renvoyer"),
"Send code": ("Enviar código", "Envoyer le code"),
"You signed in with Google, so we confirm it is you with a code to your current e-mail.": ("Inició sesión con Google, así que confirmamos que es usted con un código a su correo actual.", "Vous vous êtes connecté avec Google : nous confirmons qu'il s'agit bien de vous avec un code envoyé à votre e-mail actuel."),
"Other devices signed in to your account are signed out.": ("Se cerrará la sesión en los demás dispositivos conectados a su cuenta.", "Les autres appareils connectés à votre compte seront déconnectés."),
"Order pages": ("Páginas de pedidos", "Pages de commandes"),
"Newer orders": ("Pedidos más recientes", "Commandes plus récentes"),
"Older orders": ("Pedidos anteriores", "Commandes plus anciennes"),
"Page :page of :pages": ("Página :page de :pages", "Page :page sur :pages"),
"This month reached :amount": ("Este mes llegó a :amount", "Ce mois-ci a atteint :amount"),
"Last month closed at :amount": ("El mes pasado cerró en :amount", "Le mois dernier s'est clôturé à :amount"),
"Account approved": ("Cuenta aprobada", "Compte approuvé"),
"Due": ("Vence", "Échéance"),
"Wholesale application submitted — we will review and email you.": ("Solicitud mayorista enviada: la revisaremos y le escribiremos.", "Demande de compte grossiste envoyée : nous l'examinerons et vous écrirons."),
}
T["there"] = ("ahí", "à vous")
allkeys = {}
for loc_i, loc in enumerate(['es','fr']):
    # existing translations anywhere win (keep wording consistent)
    for f in glob.glob(f'lang/i18n/*/{loc}.json'):
        try: allkeys.setdefault(loc, {}).update({k:v for k,v in json.load(open(f)).items()})
        except Exception: pass
added = {}
for loc_i, loc in enumerate(['es','fr']):
    p = f'lang/i18n/account/{loc}.json'
    d = json.load(open(p), object_pairs_hook=collections.OrderedDict)   # re-read right before writing
    n = 0
    for k,(es,fr) in T.items():
        if k in d or k in allkeys.get(loc, {}): continue
        d[k] = (es,fr)[loc_i]; n += 1
    json.dump(d, open(p,'w'), ensure_ascii=False, indent=4)
    pass
    added[loc] = n
print(added)
