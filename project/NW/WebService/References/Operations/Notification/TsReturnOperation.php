<?php
declare(strict_types=1);

namespace NW\WebService\References\Operations\Notification;

use Exception;
use NW\WebService\Contractor\Contractor;
use NW\WebService\Contractor\Employee\Employee;
use NW\WebService\Contractor\Seller\Seller;
use NW\WebService\References\Operations\ReferencesOperation;
use NW\WebService\Status\Status;

class TsReturnOperation extends ReferencesOperation
{
    public const int TYPE_NEW = 1;
    public const int TYPE_CHANGE = 2;

    private static function getEmailsByPermit(
        int    $resellerId,
        string $event
    ): array
    {
        //TODO без реальной реализации метода, не ясно используются его аргументы или нет. Предполагаю, что это не ошибка
        // fakes the method
        return ['someemeil@example.com', 'someemeil2@example.com'];
    }

    private static function getResellerEmailFrom(int $resellerId): string
    {
        //TODO нужно реализовать метод.
        return 'contractor@example.com';
    }

    /**
     * @throws Exception
     */
    public function doOperation(): array
    {
        //TODO этот метод слишком толстый. Его нужно разбить либо на методы, либо выделить Action классы.
        //TODO Очевидно при выполнении многих TODO сложность метода уменьшится, опять же при применении тех же DTO
        $data = $this->getRequest('data');
        $resellerId = (int)$data['resellerId'];
        $notificationType = (int)$data['notificationType'];
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => false,
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];

        if (empty($resellerId)) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        if (empty($notificationType)) {
            //TODO должны быть не общие Exception, а конкреные, например EmptyNotificationException. Если такие не реализованы в проекте, то нужно сделать.
            throw new Exception('Empty notificationType', 400);
        }

        $reseller = Seller::getById($resellerId);
        //TODO проверки на "найден и не найден" стоит вынести в методы геттеры и Exception с 404 валить там. А не в каждом месте, где они используются покрывать код if-else
        if ($reseller === null) {//TODO тут всегда false будет. Я, полагаю, при реализации метода getById, возможен будет вариант null - тода этот код будет отрабатывать
            //TODO должны быть не общие Exception, а конкреные, например SellerNotFoundException. Если такие не реализованы в проекте, то нужно сделать.
            throw new Exception('Seller not found!', 404);
        }

        $client = Contractor::getById((int)$data['clientId']);
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
            //TODO должны быть не общие Exception, а конкреные, например ClientNotFoundException. Если такие не реализованы в проекте, то нужно сделать.
            throw new Exception('сlient not found!', 404);
        }

        $cFullName = $client->getFullName();
        if (empty($client->getFullName())) {
            $cFullName = $client->name;
        }

        $cr = Employee::getById((int)$data['creatorId']);
        if ($cr === null) {
            //TODO должны быть не общие Exception, а конкреные, например CreatorNotFoundException. Если такие не реализованы в проекте, то нужно сделать.
            throw new Exception('Creator not found!', 404);
        }

        $et = Employee::getById((int)$data['expertId']);
        if ($et === null) {
            throw new Exception('Expert not found!', 404);
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            //TODO предполагаю, что где-то в проекте реализована функция __().
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                'FROM' => Status::getName((int)$data['differences']['from']),
                'TO' => Status::getName((int)$data['differences']['to']),
            ], $resellerId);
        }

        //TODO я бы на DTO заменил и, если нужно проверять, чтобы конкретные данные шаблоны были заполнены, то реализовал бы прям в DTO метод проверки этих данных с возвратом bool
        $templateData = [
            'COMPLAINT_ID' => (int)$data['complaintId'],
            'COMPLAINT_NUMBER' => (string)$data['complaintNumber'],
            'CREATOR_ID' => (int)$data['creatorId'],
            'CREATOR_NAME' => $cr->getFullName(),
            'EXPERT_ID' => (int)$data['expertId'],
            'EXPERT_NAME' => $et->getFullName(),
            'CLIENT_ID' => (int)$data['clientId'],
            'CLIENT_NAME' => $cFullName,
            'CONSUMPTION_ID' => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER' => (string)$data['agreementNumber'],
            'DATE' => (string)$data['date'],
            'DIFFERENCES' => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                //TODO нужно переделать код таким образом, чтобы не приходилось 500 выдавать самим.
                // TODO Либо в DTO предусмотреть значения по-умолчанию, либо на этапах проверки данных выше, выдавать 400-е ошибки
                throw new Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = self::getResellerEmailFrom($resellerId);
        // Получаем email сотрудников из настроек
        //TODO стоит вынести в отдельный метод или Action-класс
        $emails = self::getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    //TODO здесь, очевидно, должен быть MessageDTO, а не массив. И он должен отвечать за полноты и корректность даных, а не метод отправки сообщения
                    //TODO Не стал здесь реализовывать DTO, потому что не ясно: есть абстрактный класс DTO в проекте, используется ли фреймворк или нужно все писать самому
                    'emailFrom' => $emailFrom,
                    'emailTo' => $email,
                    'subject' => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                    'message' => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    'resellerId' => $resellerId,
                    'status' => NotificationEvents::CHANGE_RETURN_STATUS,
                ]);
                $result['notificationEmployeeByEmail'] = true;

            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        //TODO стоит вынести реализацию отправки клиенту в отдельный метод или класс Action
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                //TODO здесь, очевидно, должен быть MessageDTO, а не массив. И он должен отвечать за полноты и корректность даных, а не метод отправки сообщения
                MessagesClient::sendMessage([
                    'emailFrom' => $emailFrom,
                    'emailTo' => $client->email,
                    'subject' => __('complaintClientEmailSubject', $templateData, $resellerId),
                    'message' => __('complaintClientEmailBody', $templateData, $resellerId),
                    'resellerId' => $resellerId,
                    'clientId' => $client->id,
                    'status' => NotificationEvents::CHANGE_RETURN_STATUS,
                    'differencesTo' => (int)$data['differences']['to'],
                ]);
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                $res = NotificationManager::send(
                    $resellerId,
                    $client->id,
                    NotificationEvents::CHANGE_RETURN_STATUS,
                    (int)$data['differences']['to'],
                    $templateData,
                );
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
//                if (!empty($error)) {//TODO пока закомментировал, но не ясно вообще откуда должен прийти $error. Видимо send в ответе возвращает успех или ошибку по задумке.
//                    $result['notificationClientBySms']['message'] = $error;
//                }
            }
        }

        return $result;
    }
}
