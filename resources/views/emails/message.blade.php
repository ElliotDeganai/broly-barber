{{--
    Gabarit unique de tous les emails du studio.

    Mise en page en tableaux et styles en ligne : c'est la seule façon d'obtenir
    un rendu fiable dans les clients de messagerie. Outlook ignore la plupart des
    feuilles de style, et Gmail retire les balises <style> sur mobile.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#050507;font-family:Arial,Helvetica,sans-serif;">

    {{-- Aperçu affiché dans la liste des messages, avant ouverture --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preview ?? $title }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#050507;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:560px;background:#0C0E0B;border:1px solid rgba(105,230,75,.22);border-radius:10px;">

                    {{-- Liseré vert, signature visuelle du studio --}}
                    <tr><td style="height:3px;background:#69E64B;font-size:0;line-height:0;">&nbsp;</td></tr>

                    {{--
                        Bandeau : visuel du site en fond, logo par-dessus.

                        Outlook sur Windows utilise le moteur de rendu de Word,
                        qui ignore background-image. Le bloc VML ci-dessous lui
                        fournit le même fond ; les autres clients l'ignorent.
                    --}}
                    <tr>
                        <td background="{{ $background ?? '' }}"
                            bgcolor="#0C0E0B"
                            valign="middle"
                            style="background-color:#0C0E0B;
                                   @if ($background ?? null) background-image:url('{{ $background }}');
                                   background-position:center 34%;background-size:cover; @endif
                                   border-radius:10px 10px 0 0;">

                            @if ($background ?? null)
                                <!--[if gte mso 9]>
                                <v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false"
                                        style="width:560px;height:150px;">
                                    <v:fill type="frame" src="{{ $background }}" color="#0C0E0B" />
                                    <v:textbox inset="0,0,0,0">
                                <![endif]-->
                            @endif

                            {{-- Voile sombre : le logo doit rester lisible quelle
                                 que soit la zone de la photo qui apparaît. --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                   style="background-color:rgba(5,5,7,.62);">
                                <tr>
                                    <td align="center" style="padding:30px 24px;">
                                        @if ($logo ?? null)
                                            {{-- Largeur fixe : sans elle, Outlook
                                                 affiche l'image à sa taille réelle. --}}
                                            <img src="{{ $logo }}" width="190" alt="Broly Asian Barber"
                                                 style="display:block;width:190px;max-width:70%;height:auto;border:0;">
                                        @else
                                            <p style="margin:0;font-size:20px;font-weight:bold;color:#FFFFFF;letter-spacing:.5px;">
                                                BROLY <span style="color:#FFD52A;">ASIAN BARBER</span>
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            @if ($background ?? null)
                                <!--[if gte mso 9]>
                                    </v:textbox>
                                </v:rect>
                                <![endif]-->
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:26px 28px 0;">
                            <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#69E64B;">
                                {{ $title }}
                            </h1>

                            @foreach ($lines as $line)
                                <p style="margin:0 0 14px;font-size:15px;line-height:1.65;color:#D8DDD6;">
                                    {!! $line !!}
                                </p>
                            @endforeach
                        </td>
                    </tr>

                    {{-- Encadré de détails : récapitulatif du rendez-vous --}}
                    @if (!empty($details))
                        <tr>
                            <td style="padding:8px 28px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                       style="background:#111410;border:1px solid rgba(105,230,75,.18);border-radius:8px;">
                                    @foreach ($details as $label => $value)
                                        <tr>
                                            <td style="padding:10px 16px;font-size:13px;color:#8D9489;">{{ $label }}</td>
                                            <td style="padding:10px 16px;font-size:14px;color:#FFFFFF;text-align:right;font-weight:bold;">
                                                {{ $value }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif

                    @if (!empty($action) && !empty($url))
                        <tr>
                            <td style="padding:24px 28px 0;text-align:center;">
                                {{-- Bordure épaisse plutôt qu'un fond : le vert plein
                                     rend le texte illisible dans les clients qui
                                     forcent leur propre couleur de police. --}}
                                <a href="{{ $url }}"
                                   style="display:inline-block;padding:14px 30px;background:#69E64B;color:#04120A;
                                          font-size:15px;font-weight:bold;text-decoration:none;border-radius:6px;">
                                    {{ $action }}
                                </a>
                            </td>
                        </tr>
                    @endif

                    @if (!empty($note))
                        <tr>
                            <td style="padding:20px 28px 0;">
                                <p style="margin:0;font-size:13px;line-height:1.6;color:#8D9489;">{{ $note }}</p>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:26px 28px 28px;">
                            <div style="border-top:1px solid rgba(105,230,75,.15);padding-top:18px;">
                                <p style="margin:0;font-size:12px;line-height:1.6;color:#6E756B;">
                                    Broly Asian Barber — studio privé sur rendez-vous.<br>
                                    Aucun paiement n'est encaissé en ligne : le règlement s'effectue au studio.
                                </p>
                            </div>
                        </td>
                    </tr>
                </table>

                @if (!empty($url))
                    <p style="max-width:560px;margin:18px auto 0;font-size:11px;line-height:1.6;color:#5A6157;text-align:center;">
                        Si le bouton ne fonctionne pas, copiez cette adresse :<br>
                        <span style="color:#8D9489;word-break:break-all;">{{ $url }}</span>
                    </p>
                @endif

            </td>
        </tr>
    </table>
</body>
</html>
